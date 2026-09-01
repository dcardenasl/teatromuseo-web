<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Support/PublicPaths.php';

$contractPath = dirname(__DIR__) . '/docs/contracts/public-routes.json';
$expected = file_get_contents($contractPath);
if (! is_string($expected)) {
    fwrite(STDERR, "Cannot read public route contract: {$contractPath}\n");
    exit(1);
}

$actual = json_encode(
    \App\Support\PublicPaths::publicRouteContract(),
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
) . PHP_EOL;

if (trim($expected) !== trim($actual)) {
    fwrite(STDERR, "Public route contract is out of date. Run:\n");
    fwrite(STDERR, "  php scripts/export-public-route-contract.php > docs/contracts/public-routes.json\n");
    exit(1);
}

fwrite(STDOUT, "Public route contract is up to date.\n");
