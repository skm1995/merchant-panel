<?php

declare(strict_types=1);

// Fails bin/ci when the merged line coverage of all test suites falls below the minimum. The
// minimum trails the measured coverage by about two points: raise it when coverage grows, so
// it only ever moves up.
const MINIMUM_LINE_COVERAGE = 95.0;
const CLOVER_FILE = __DIR__ . '/../../build/coverage/clover.xml';
const SUMMARY_FILE = __DIR__ . '/../../build/coverage/summary.md';
const SUMMARY_FORMAT = "Line coverage of all suites: %.1f%% (%d of %d lines), minimum %.1f%%.\n";
const BELOW_MINIMUM_MESSAGE = "Coverage fell below the minimum; add tests for the new code.\n";

$clover = simplexml_load_file(CLOVER_FILE);
$metrics = $clover === false ? null : $clover->project->metrics;

if ($metrics === null) {
    fwrite(STDERR, sprintf("Cannot read %s.\n", CLOVER_FILE));
    exit(1);
}

$lines = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];
$percent = $lines === 0 ? 0.0 : 100 * $covered / $lines;

$summary = sprintf(SUMMARY_FORMAT, $percent, $covered, $lines, MINIMUM_LINE_COVERAGE);
fwrite(STDOUT, $summary);
// GitHub Actions adds this line to the run's summary page.
file_put_contents(SUMMARY_FILE, $summary);

if ($percent < MINIMUM_LINE_COVERAGE) {
    fwrite(STDERR, BELOW_MINIMUM_MESSAGE);
    exit(1);
}
