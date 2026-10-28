<?php

declare(strict_types=1);

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Driver\Selector;
use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\CodeCoverage\Report\PHP;
use SebastianBergmann\FileIterator\Facade;

// Measures one request of the test web server with PCOV and leaves the result next to the
// test suites' own coverage files, which bin/ci merges into one report.
const COVERAGE_SOURCE = '/app/backend/src';
const COVERAGE_DIRECTORY = '/app/backend/.phpunit.cache/coverage';
const ANALYSIS_CACHE_DIRECTORY = '/app/backend/.phpunit.cache/coverage-analysis';
const COVERAGE_FILE_FORMAT = '%s/request-%s.cov';
const SOURCE_SUFFIX = '.php';
const UNKNOWN_REQUEST = 'request';

if (!is_dir(COVERAGE_DIRECTORY)) {
    return;
}

require_once '/app/backend/vendor/autoload.php';

$filter = new Filter();
$filter->includeFiles(new Facade()->getFilesAsArray(COVERAGE_SOURCE, SOURCE_SUFFIX));
$coverage = new CodeCoverage(new Selector()->forLineCoverage($filter), $filter);
// Each worker would otherwise parse every source file again to find its executable lines.
$coverage->cacheStaticAnalysis(ANALYSIS_CACHE_DIRECTORY);
$coverage->start($_SERVER['REQUEST_URI'] ?? UNKNOWN_REQUEST);

// Registered from a shutdown function, so it runs after the application's own. It saves the
// session and sends the response before writing the coverage: PHP would otherwise save the
// session only after this, and the browser's next request would still find it signed out.
register_shutdown_function(static function () use ($coverage): void {
    register_shutdown_function(static function () use ($coverage): void {
        session_write_close();
        fastcgi_finish_request();
        $coverage->stop();
        new PHP()->process($coverage, sprintf(COVERAGE_FILE_FORMAT, COVERAGE_DIRECTORY, bin2hex(random_bytes(8))));
    });
});
