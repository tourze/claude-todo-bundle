<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\ClaudeTodoBundle\Service\SleepService;

/**
 * @internal
 */
#[CoversClass(SleepService::class)]
final class SleepServiceTest extends TestCase
{
    private SleepService $sleepService;

    protected function setUp(): void
    {
        $this->sleepService = new SleepService();
    }

    public function testSleepIsCallable(): void
    {
        $startTime = microtime(true);
        $this->sleepService->sleep(0);
        $endTime = microtime(true);

        $this->assertGreaterThanOrEqual($startTime, $endTime);
    }

    public function testRandomSleepIsCallable(): void
    {
        $startTime = microtime(true);
        $this->sleepService->randomSleep(0, 0);
        $endTime = microtime(true);

        $this->assertGreaterThanOrEqual($startTime, $endTime);
    }
}
