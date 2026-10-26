<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Psr\Container\ContainerInterface;

/**
 * Web entry point: starts the session for browser requests, registers every discovered controller, and
 * dispatches the request. Controllers are built through the container.
 */
final readonly class HttpApplication
{
    public function __construct(
        private ContainerInterface $container,
        private Router $router,
        private Session $session,
    ) {
    }

    public function run(Request $request): void
    {
        if (!$request->isApi()) {
            $this->session->start();
        }

        foreach (ControllerDiscovery::findInSource() as $controller) {
            $this->router->registerController($this->container->get($controller));
        }

        $this->router->dispatch($request);
    }
}
