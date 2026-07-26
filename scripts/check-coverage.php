#!/usr/bin/env php
<?php

declare(strict_types=1);

$threshold = isset($argv[1]) ? (float) $argv[1] : 0.0;
$cloverPath = $argv[2] ?? __DIR__ . '/../build/logs/clover.xml';

if (! is_readable($cloverPath)) {
    fwrite(STDERR, "check-coverage: Clover XML not found at {$cloverPath}.\n");
    exit(2);
}

$xml = @simplexml_load_file($cloverPath);
if ($xml === false || ! isset($xml->project->metrics)) {
    fwrite(STDERR, "check-coverage: malformed Clover XML at {$cloverPath}.\n");
    exit(2);
}

$metrics = $xml->project->metrics;
$total = (int) ($metrics['statements'] ?? 0);
$covered = (int) ($metrics['coveredstatements'] ?? 0);

if ($total === 0) {
    fwrite(STDERR, "check-coverage: Clover reports zero statements.\n");
    exit(2);
}

$percent = ($covered / $total) * 100.0;
if ($percent + 0.001 < $threshold) {
    fwrite(STDERR, sprintf(
        "check-coverage: FAIL — %.2f%% is below %.2f%% (%d/%d).\n",
        $percent,
        $threshold,
        $covered,
        $total,
    ));
    exit(1);
}

printf(
    "check-coverage: PASS — %.2f%% >= %.2f%% (%d/%d).\n",
    $percent,
    $threshold,
    $covered,
    $total,
);
