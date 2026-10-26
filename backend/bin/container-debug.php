<?php

declare(strict_types=1);

use App\Shared\DependencyInjection\ContainerFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

const LINE_FORMAT = '%s%s    => %s%s';

// Lists every discovered service binding and where it comes from; concrete classes not
// listed here are autowired from their constructors.
foreach (ContainerFactory::discovery()->describe() as $id => $source) {
    fwrite(STDOUT, sprintf(LINE_FORMAT, $id, PHP_EOL, $source, PHP_EOL));
}
