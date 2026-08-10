<?php

declare(strict_types=1);

namespace App\PageDelivery;

use DateTimeImmutable;

final readonly class PageSnapshot
{
    /** @param array<string, mixed> $envelope */
    public function __construct(
        public string $key,
        public array $envelope,
        public DateTimeImmutable $generatedAt,
        public DateTimeImmutable $expiresAt,
        public ?string $revision = null,
    ) {
    }
}
