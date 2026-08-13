<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries;

use App\Libraries\SingleFlightLock;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class SingleFlightLockTest extends CIUnitTestCase
{
    private string $lockDir;

    protected function setUp(): void
    {
        parent::setUp();
        $baseDir = defined('WRITABLEPATH') ? WRITABLEPATH : './writable/';
        $this->lockDir = rtrim($baseDir, '/') . '/cache/test_locks_' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->lockDir)) {
            $files = glob($this->lockDir . '/*');
            if (is_array($files)) {
                foreach ($files as $file) {
                    @unlink($file);
                }
            }
            @rmdir($this->lockDir);
        }
        parent::tearDown();
    }

    public function testSingleFlightExecutesMissWhenCacheIsEmpty(): void
    {
        $lock = new SingleFlightLock($this->lockDir);
        $missCalled = 0;

        $result = $lock->single(
            'test_key',
            fn () => null,
            function () use (&$missCalled) {
                $missCalled++;
                return 'fresh_data';
            }
        );

        $this->assertSame('fresh_data', $result);
        $this->assertSame(1, $missCalled);
    }

    public function testSingleFlightReturnsRecheckIfAvailable(): void
    {
        $lock = new SingleFlightLock($this->lockDir);
        $missCalled = 0;

        $result = $lock->single(
            'test_key',
            fn () => 'cached_data',
            function () use (&$missCalled) {
                $missCalled++;
                return 'fresh_data';
            }
        );

        $this->assertSame('cached_data', $result);
        $this->assertSame(0, $missCalled);
    }

    public function testSingleFlightAcquiresExclusiveLockAndBlocksOthers(): void
    {
        $lock = new SingleFlightLock($this->lockDir, 0.2, 10_000);

        // We can simulate a lock held externally by opening the lock file and locking it
        $key = 'concurrent_key';
        $lockFile = $this->lockDir . '/' . hash('sha256', $key) . '.lock';
        @mkdir($this->lockDir, 0750, true);
        $handle = fopen($lockFile, 'c');
        $this->assertNotFalse($handle);

        // Lock it exclusively
        $this->assertTrue(flock($handle, LOCK_EX));

        // Now if we run single(), it should block, timeout, and degrade to calling onMiss() directly
        $missCalled = 0;
        $result = $lock->single(
            $key,
            fn () => 'should_not_recheck_due_to_timeout',
            function () use (&$missCalled) {
                $missCalled++;
                return 'fallback_value';
            }
        );

        $this->assertSame('fallback_value', $result);
        $this->assertSame(1, $missCalled);

        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
