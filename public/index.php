<?php

declare(strict_types=1);

use App\Shared\DependencyInjection\ContainerFactory;
use App\Shared\Http\HttpApplication;
use App\Shared\Http\Request;

require dirname(__DIR__) . '/vendor/autoload.php';

ContainerFactory::create()->get(HttpApplication::class)->run(Request::fromGlobals());
