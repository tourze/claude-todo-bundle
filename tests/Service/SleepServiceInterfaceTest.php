<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\ClaudeTodoBundle\Service\SleepServiceInterface;

/**
 * @internal
 */
#[CoversClass(SleepServiceInterface::class)]
final class SleepServiceInterfaceTest extends TestCase
{
    public function testInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(SleepServiceInterface::class));
    }

    public function testInterfaceMethodsAreProperlyDefined(): void
    {
        $reflection = new \ReflectionClass(SleepServiceInterface::class);

        $this->assertTrue($reflection->hasMethod('sleep'));
        $this->assertTrue($reflection->hasMethod('randomSleep'));

        $sleepMethod = $reflection->getMethod('sleep');
        $this->assertSame('sleep', $sleepMethod->getName());
        $this->assertCount(1, $sleepMethod->getParameters());

        $randomSleepMethod = $reflection->getMethod('randomSleep');
        $this->assertSame('randomSleep', $randomSleepMethod->getName());
        $this->assertCount(2, $randomSleepMethod->getParameters());
    }
}
