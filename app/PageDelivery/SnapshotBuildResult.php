<?php

declare(strict_types=1);

namespace App\PageDelivery;

final readonly class SnapshotBuildResult
{
    private function __construct(
        public string $state,
        public ?PageDeliveryResponse $response = null,
        public ?string $revision = null,
        public ?string $message = null,
    ) {
    }

    public static function built(PageDeliveryResponse $response, string $revision): self
    {
        return new self('built', $response, $revision);
    }

    public static function skipped(?string $revision = null): self
    {
        return new self('skipped', null, $revision);
    }

    public static function busy(): self
    {
        return new self('busy', null, null, 'Another snapshot builder owns this identity.');
    }

    public static function failed(string $message, ?PageDeliveryResponse $response = null): self
    {
        return new self('failed', $response, null, $message);
    }

    public function isSuccessful(): bool
    {
        return in_array($this->state, ['built', 'skipped'], true);
    }
}
