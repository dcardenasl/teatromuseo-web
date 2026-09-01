<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Support/PublicPaths.php';

echo json_encode(
    \App\Support\PublicPaths::publicRouteContract(),
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
) . PHP_EOL;
