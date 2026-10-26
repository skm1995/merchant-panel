<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Http\Middleware\AccessLogMiddleware;
use App\Shared\Http\Middleware\MiddlewarePipeline;
use App\Shared\Http\Middleware\RequestIdMiddleware;
use App\Shared\Http\Middleware\UnhandledErrorMiddleware;
use Psr\Container\ContainerInterface;

/**
 * Web entry point: registers every discovered controller and handles the request through
 * the policies every request shares (request id, access log, unexpected errors) and the
 * router. Controllers are built through the container.
 */
final readonly class HttpApplication
{
    public function __construct(
        private ContainerInterface $container,
        private Router $router,
        private RequestIdMiddleware $requestId,
        private AccessLogMiddleware $accessLog,
        private UnhandledErrorMiddleware $unhandledError,
    ) {
    }

    public function run(Request $request): void
    {
        foreach (ControllerDiscovery::findInSource() as $controller) {
            $this->router->registerController($this->container->get($controller));
        }

        new MiddlewarePipeline([$this->requestId, $this->accessLog, $this->unhandledError], $this->router)->handle($request);
    }
}
