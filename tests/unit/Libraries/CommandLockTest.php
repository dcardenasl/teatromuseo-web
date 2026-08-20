<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries;

use App\Libraries\CommandLock;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class CommandLockTest extends CIUnitTestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = (defined('WRITABLEPATH') ? WRITABLEPATH : './writable/')
            . 'cache/test-command-lock-' . bin2hex(random_bytes(4)) . '/job.lock';
    }

    protected function tearDown(): void
    {
        $directory = dirname($this->path);
        if (is_dir($directory)) {
            foreach (glob($directory . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($directory);
        }

        parent::tearDown();
    }

    public function testOnlyOneProcessOwnsTheScheduledJobAtATime(): void
    {
        $first = new CommandLock($this->path);
        $second = new CommandLock($this->path);

        $this->assertTrue($first->acquire());
        $this->assertFalse($second->acquire());

        $first->release();

        $this->assertTrue($second->acquire());
        $second->release();
    }
}
