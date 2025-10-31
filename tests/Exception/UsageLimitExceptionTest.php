<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\ClaudeTodoBundle\Exception\ClaudeTodoException;
use Tourze\ClaudeTodoBundle\Exception\UsageLimitException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(UsageLimitException::class)]
final class UsageLimitExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionWithWaitTime(): void
    {
        $waitUntil = time() + 3600; // 1 hour from now
        $exception = UsageLimitException::withWaitTime($waitUntil);

        $this->assertInstanceOf(UsageLimitException::class, $exception);
        $this->assertInstanceOf(ClaudeTodoException::class, $exception);
        $this->assertEquals($waitUntil, $exception->getWaitUntil());
        $this->assertStringContainsString('Claude AI usage limit reached', $exception->getMessage());
    }

    public function testExceptionCalculatesWaitSeconds(): void
    {
        $currentTime = time();
        $waitUntil = $currentTime + 300; // 5 minutes from now
        $exception = UsageLimitException::withWaitTime($waitUntil);

        // The wait seconds should be approximately 300 (may vary by 1-2 seconds due to execution time)
        $this->assertGreaterThanOrEqual(298, $exception->getWaitSeconds());
        $this->assertLessThanOrEqual(302, $exception->getWaitSeconds());
    }

    public function testExceptionWithPastWaitTime(): void
    {
        $waitUntil = time() - 3600; // 1 hour ago
        $exception = UsageLimitException::withWaitTime($waitUntil);

        $this->assertEquals(0, $exception->getWaitSeconds());
    }
}
