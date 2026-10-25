<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$phpSourceDirectory = '/src';
$phpPublicDirectory = '/public';
$phpTestsDirectory = '/tests';
$phpViewDirectory = '/views';
$phpMigrationDirectory = '/database/migrations';
$phpToolsDirectory = '/tools';
$phpConfigDirectory = '/config';
$phpBinDirectory = '/bin';
$phpMigrationConfiguration = '/phinx.php';
$phpCodingStandard = '@PSR12';

$finder = Finder::create()
    ->in([
        __DIR__ . $phpSourceDirectory,
        __DIR__ . $phpPublicDirectory,
        __DIR__ . $phpTestsDirectory,
        __DIR__ . $phpViewDirectory,
        __DIR__ . $phpMigrationDirectory,
        __DIR__ . $phpToolsDirectory,
        __DIR__ . $phpConfigDirectory,
        __DIR__ . $phpBinDirectory,
    ]);

$finder->append([__DIR__ . $phpMigrationConfiguration]);

return new Config()
    ->setRules([
        $phpCodingStandard => true,
    ])
    ->setFinder($finder);
