<?php

declare(strict_types=1);

namespace App\Shared\DependencyInjection;

use App\Shared\DependencyInjection\Exception\ContainerException;
use Closure;
use LogicException;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Derives container definitions from the source code instead of a configuration file:
 * an application interface with one implementation is bound to it, #[DefaultImplementation] chooses
 * among several, and #[ServiceFactory] methods build the service their return type names.
 */
final readonly class ServiceDiscovery
{
    private const string BINDINGS_KEY = 'bindings';
    private const string DEFAULTS_KEY = 'defaults';
    private const string AMBIGUOUS_KEY = 'ambiguous';
    private const string FACTORIES_KEY = 'factories';
    private const string SINGLE_SOURCE_FORMAT = 'single implementation %s';
    private const string DEFAULT_SOURCE_FORMAT = '#[DefaultImplementation] %s';
    private const string FACTORY_SOURCE_FORMAT = '#[ServiceFactory] %s::%s()';
    private const string AMBIGUOUS_SOURCE_FORMAT = 'ambiguous: %s';
    private const string AMBIGUOUS_MESSAGE_FORMAT = '%s has several implementations: %s; mark one with #[DefaultImplementation].';
    private const string DUPLICATE_FACTORY_FORMAT = 'Both %s and %s are #[ServiceFactory] methods for %s.';
    private const string FACTORY_TYPE_FORMAT = '#[ServiceFactory] %s::%s() must declare a class or interface return type.';
    private const string METHOD_SEPARATOR = '::';
    private const string LIST_SEPARATOR = ', ';

    /**
     * @param array<string, class-string> $bindings single implementations keyed by interface
     * @param array<string, class-string> $defaults default implementations keyed by interface
     * @param array<string, list<class-string>> $ambiguous candidate implementations keyed by interface
     * @param array<string, array{class-string, string, bool}> $factories class, method, and whether it is static, keyed by service id
     */
    private function __construct(
        private array $bindings,
        private array $defaults,
        private array $ambiguous,
        private array $factories,
    ) {
    }

    /**
     * Only interfaces among the scanned classes are bound automatically; interfaces from PHP
     * or vendor packages, such as Stringable or a PSR interface, need a #[ServiceFactory].
     *
     * @param list<class-string> $classes
     */
    public static function fromClasses(array $classes): self
    {
        $scanned = array_flip($classes);
        $candidates = [];
        $defaults = [];
        $factories = [];

        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);

            if ($reflection->isInstantiable()) {
                $isDefault = $reflection->getAttributes(DefaultImplementation::class) !== [];

                foreach (array_intersect_key(array_flip($reflection->getInterfaceNames()), $scanned) as $interface => $position) {
                    $candidates[$interface][] = $class;

                    if ($isDefault) {
                        $defaults[$interface] = $class;
                    }
                }
            }

            foreach ($reflection->getMethods() as $method) {
                if ($method->getAttributes(ServiceFactory::class) === []) {
                    continue;
                }

                $type = $method->getReturnType();

                if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                    throw new LogicException(sprintf(self::FACTORY_TYPE_FORMAT, $class, $method->getName()));
                }

                $id = $type->getName();

                if (isset($factories[$id])) {
                    throw new LogicException(sprintf(
                        self::DUPLICATE_FACTORY_FORMAT,
                        $factories[$id][0] . self::METHOD_SEPARATOR . $factories[$id][1],
                        $class . self::METHOD_SEPARATOR . $method->getName(),
                        $id,
                    ));
                }

                $factories[$id] = [$class, $method->getName(), $method->isStatic()];
            }
        }

        $bindings = [];
        $ambiguous = [];

        foreach ($candidates as $interface => $implementations) {
            if (isset($defaults[$interface])) {
                continue;
            }

            if (count($implementations) === 1) {
                $bindings[$interface] = $implementations[0];
            } else {
                sort($implementations);
                $ambiguous[$interface] = $implementations;
            }
        }

        return new self($bindings, $defaults, $ambiguous, $factories);
    }

    /**
     * @param array{
     *     bindings: array<string, class-string>,
     *     defaults: array<string, class-string>,
     *     ambiguous: array<string, list<class-string>>,
     *     factories: array<string, array{class-string, string, bool}>
     * } $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data[self::BINDINGS_KEY],
            $data[self::DEFAULTS_KEY],
            $data[self::AMBIGUOUS_KEY],
            $data[self::FACTORIES_KEY],
        );
    }

    /**
     * @return array{
     *     bindings: array<string, class-string>,
     *     defaults: array<string, class-string>,
     *     ambiguous: array<string, list<class-string>>,
     *     factories: array<string, array{class-string, string, bool}>
     * }
     */
    public function toArray(): array
    {
        return [
            self::BINDINGS_KEY => $this->bindings,
            self::DEFAULTS_KEY => $this->defaults,
            self::AMBIGUOUS_KEY => $this->ambiguous,
            self::FACTORIES_KEY => $this->factories,
        ];
    }

    /** @return array<string, class-string|Closure(Container): object> */
    public function definitions(): array
    {
        $definitions = [...$this->bindings, ...$this->defaults];

        foreach ($this->ambiguous as $interface => $implementations) {
            $message = sprintf(self::AMBIGUOUS_MESSAGE_FORMAT, $interface, implode(self::LIST_SEPARATOR, $implementations));
            $definitions[$interface] = static fn (): never => throw new ContainerException($message);
        }

        foreach ($this->factories as $id => [$class, $method, $static]) {
            $definitions[$id] = static fn (Container $container): object => $container->callFactory($class, $method, $static);
        }

        return $definitions;
    }

    /** @return array<string, string> how each service id is provided */
    public function describe(): array
    {
        $sources = [];

        foreach ($this->bindings as $interface => $class) {
            $sources[$interface] = sprintf(self::SINGLE_SOURCE_FORMAT, $class);
        }

        foreach ($this->defaults as $interface => $class) {
            $sources[$interface] = sprintf(self::DEFAULT_SOURCE_FORMAT, $class);
        }

        foreach ($this->ambiguous as $interface => $implementations) {
            $sources[$interface] = sprintf(self::AMBIGUOUS_SOURCE_FORMAT, implode(self::LIST_SEPARATOR, $implementations));
        }

        foreach ($this->factories as $id => [$class, $method]) {
            $sources[$id] = sprintf(self::FACTORY_SOURCE_FORMAT, $class, $method);
        }

        ksort($sources);

        return $sources;
    }
}
