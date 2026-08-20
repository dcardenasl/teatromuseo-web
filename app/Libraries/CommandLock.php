<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Non-blocking process lock for scheduled work.
 *
 * The lock target is never deleted. `flock()` releases it automatically when
 * a PHP process exits, including an abnormal termination, so a stale lock file
 * can never permanently disable a scheduled job.
 */
final class CommandLock
{
    /** @var resource|null */
    private $handle = null;

    public function __construct(private readonly string $path)
    {
    }

    public function acquire(): bool
    {
        $directory = dirname($this->path);
        if (! is_dir($directory) && ! @mkdir($directory, 0750, true) && ! is_dir($directory)) {
            return false;
        }

        $handle = @fopen($this->path, 'c');
        if ($handle === false || ! @flock($handle, LOCK_EX | LOCK_NB)) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            return false;
        }

        $this->handle = $handle;

        return true;
    }

    public function release(): void
    {
        if (! is_resource($this->handle)) {
            return;
        }

        @flock($this->handle, LOCK_UN);
        fclose($this->handle);
        $this->handle = null;
    }

    public function __destruct()
    {
        $this->release();
    }
}
