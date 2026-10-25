<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Psr\Container\ContainerInterface;

/**
 * Web entry point: starts the session, registers every discovered controller, and
 * dispatches the request. Controllers are built through the container.
 */
final class HttpApplication
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly Router $router,
        private readonly Session $session,
    ) {
    }

    public function run(Request $request): void
    {
        $this->session->start();

        foreach (ControllerDiscovery::fromSource() as $controller) {
            $this->router->registerController($this->container->get($controller));
        }

        $this->router->dispatch($request);
    }
}
