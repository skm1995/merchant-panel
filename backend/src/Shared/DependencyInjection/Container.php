<?php

declare(strict_types=1);

namespace App\Shared\DependencyInjection;

use App\Shared\Config\Environment;
use App\Shared\DependencyInjection\Exception\ContainerException;
use App\Shared\DependencyInjection\Exception\ServiceNotFoundException;
use Closure;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * PSR-11 container that autowires constructors by their parameter types.
 *
 * Definitions bind an interface to the class implementing it, or any id to a factory for
 * objects that need custom construction. Every other class is built by resolving its
 * constructor parameters recursively: class types from the container, #[Autowire] to pick
 * a specific service, #[Env] for configuration, and default values otherwise. Each service
 * is created once per container.
 */
final class Container implements ContainerInterface
{
    private const string CIRCULAR_FORMAT = 'Circular dependency: %s.';
    private const string NOT_INSTANTIABLE_FORMAT = '%s cannot be created; bind it to an implementation in config/services.php.';
    private const string UNRESOLVABLE_FORMAT = 'Parameter $%s of %s needs a class type, #[Env], or a default value.';
    private const string UNSUPPORTED_ENV_FORMAT = 'Parameter $%s of %s reads #[Env] but only string and int are supported.';
    private const string CHAIN_SEPARATOR = ' -> ';
    private const string FACTORY_RESULT_FORMAT = '#[ServiceFactory] %s::%s() did not return an object.';
    private const string STRING_TYPE = 'string';
    private const string INT_TYPE = 'int';

    /** @var array<string, object> */
    private array $services = [];
    /** @var array<string, true> */
    private array $resolving = [];

    /**
     * @param array<string, class-string|Closure(self): object> $definitions
     */
    public function __construct(private readonly array $definitions = [])
    {
        $this->services[self::class] = $this;
        $this->services[ContainerInterface::class] = $this;
    }

    /**
     * @template T of object
     * @param string|class-string<T> $id
     * @return ($id is class-string<T> ? T : object)
     */
    public function get(string $id): object
    {
        if (isset($this->services[$id])) {
            return $this->services[$id];
        }

        if (isset($this->resolving[$id])) {
            throw new ContainerException(sprintf(
                self::CIRCULAR_FORMAT,
                implode(self::CHAIN_SEPARATOR, [...array_keys($this->resolving), $id]),
            ));
        }

        $this->resolving[$id] = true;

        try {
            return $this->services[$id] = $this->create($id);
        } finally {
            unset($this->resolving[$id]);
        }
    }

    /**
     * Calls a factory method with autowired arguments; instance factories are called on the
     * service of their class.
     *
     * @param class-string $class
     */
    public function callFactory(string $class, string $method, bool $static): object
    {
        $reflection = new ReflectionClass($class)->getMethod($method);
        $arguments = array_map(
            fn (ReflectionParameter $parameter): mixed => $this->resolveArgument($parameter, $class),
            $reflection->getParameters(),
        );
        $service = $reflection->invokeArgs($static ? null : $this->get($class), $arguments);

        return is_object($service) ? $service : throw new ContainerException(sprintf(self::FACTORY_RESULT_FORMAT, $class, $method));
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]) || isset($this->definitions[$id]) || class_exists($id);
    }

    private function create(string $id): object
    {
        $definition = $this->definitions[$id] ?? null;

        if ($definition instanceof Closure) {
            return $definition($this);
        }

        if (is_string($definition)) {
            return $this->get($definition);
        }

        if (!class_exists($id) && !interface_exists($id)) {
            throw new ServiceNotFoundException($id);
        }

        return $this->autowire($id);
    }

    /** @param class-string $class */
    private function autowire(string $class): object
    {
        $reflection = new ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            throw new ContainerException(sprintf(self::NOT_INSTANTIABLE_FORMAT, $class));
        }

        $arguments = array_map(
            fn (ReflectionParameter $parameter): mixed => $this->resolveArgument($parameter, $class),
            $reflection->getConstructor()?->getParameters() ?? [],
        );

        return $reflection->newInstanceArgs($arguments);
    }

    private function resolveArgument(ReflectionParameter $parameter, string $class): mixed
    {
        $autowire = ($parameter->getAttributes(Autowire::class)[0] ?? null)?->newInstance();

        if ($autowire !== null) {
            return $this->get($autowire->service);
        }

        $env = ($parameter->getAttributes(Env::class)[0] ?? null)?->newInstance();
        $type = $parameter->getType();

        if ($env !== null) {
            return $this->environmentValue($env, $type, $parameter, $class);
        }

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin() && $this->canBuild($type->getName())) {
            return $this->get($type->getName());
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw new ContainerException(sprintf(self::UNRESOLVABLE_FORMAT, $parameter->getName(), $class));
    }

    private function canBuild(string $id): bool
    {
        return isset($this->services[$id])
            || isset($this->definitions[$id])
            || (class_exists($id) && new ReflectionClass($id)->isInstantiable())
            || interface_exists($id);
    }

    private function environmentValue(Env $env, mixed $type, ReflectionParameter $parameter, string $class): string|int
    {
        $typeName = $type instanceof ReflectionNamedType ? $type->getName() : null;

        return match ($typeName) {
            self::STRING_TYPE => Environment::get($env->key),
            self::INT_TYPE => Environment::positiveInteger($env->key),
            default => throw new ContainerException(sprintf(self::UNSUPPORTED_ENV_FORMAT, $parameter->getName(), $class)),
        };
    }
}
