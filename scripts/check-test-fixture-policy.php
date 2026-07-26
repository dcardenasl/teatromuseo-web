<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$adapter = $root . '/tests/_support/Libraries/DeterministicDomainAdapter.php';
$contents = (string) file_get_contents($adapter);

if (preg_match('#public/\(es\|en\)#', $contents) === 1 || preg_match("/\['es'\s*=>.*'en'/", $contents) === 1) {
    fwrite(STDERR, "Fixture policy violation: the deterministic adapter must not encode a fixed locale set.\n");
    exit(1);
}

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/tests'));
foreach ($iterator as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    $contents = (string) file_get_contents($path);
    if (preg_match('/Database::seeder|Seeder::class/', $contents) === 1) {
        fwrite(STDERR, $path . ": Web tests must not execute Domain seeders.\n");
        exit(1);
    }
}

fwrite(STDOUT, "Fixture policy passed: Web tests use an unconstrained locale adapter.\n");
