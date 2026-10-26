<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Http\Middleware;

use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Middleware\MiddlewareInterface;
use App\Shared\Http\Middleware\MiddlewarePipeline;
use App\Shared\Http\Middleware\RequestHandlerInterface;
use App\Shared\Http\Request;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Support\RecordingHandler;

final class MiddlewarePipelineTest extends TestCase
{
    private const string PATH = '/';

    public function testRunsMiddlewareInOrderAroundTheHandler(): void
    {
        $calls = new \ArrayObject();
        $handler = new RecordingHandler();
        $pipeline = new MiddlewarePipeline([self::tracing($calls, 'outer'), self::tracing($calls, 'inner')], $handler);

        $pipeline->handle(new Request(HttpMethodEnum::GET->value, self::PATH));

        self::assertSame(['outer:before', 'inner:before', 'inner:after', 'outer:after'], $calls->getArrayCopy());
        self::assertCount(1, $handler->handled);
    }

    public function testAnEmptyPipelineCallsTheHandler(): void
    {
        $handler = new RecordingHandler();

        new MiddlewarePipeline([], $handler)->handle(new Request(HttpMethodEnum::GET->value, self::PATH));

        self::assertCount(1, $handler->handled);
    }

    /** @param \ArrayObject<int, string> $calls */
    private static function tracing(\ArrayObject $calls, string $name): MiddlewareInterface
    {
        return new readonly class ($calls, $name) implements MiddlewareInterface {
            /** @param \ArrayObject<int, string> $calls */
            public function __construct(private \ArrayObject $calls, private string $name)
            {
            }

            public function process(Request $request, RequestHandlerInterface $next): void
            {
                $this->calls[] = $this->name . ':before';
                $next->handle($request);
                $this->calls[] = $this->name . ':after';
            }
        };
    }
}
