<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\ClaudeTodoBundle\Exception\ExecutionException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(ExecutionException::class)]
final class ExecutionExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionWithTaskIdAndReason(): void
    {
        $previousException = new \Exception('Previous error');
        $exception = ExecutionException::forTask(456, 'Claude CLI not available', $previousException);

        $this->assertInstanceOf(ExecutionException::class, $exception);
        $this->assertEquals('Failed to execute task 456: Claude CLI not available', $exception->getMessage());
        $this->assertEquals(456, $exception->getTaskId());
        $this->assertSame($previousException, $exception->getPrevious());
    }

    public function testExceptionWithoutPreviousException(): void
    {
        $exception = ExecutionException::forTask(789, 'Process timeout');

        $this->assertEquals('Failed to execute task 789: Process timeout', $exception->getMessage());
        $this->assertEquals(789, $exception->getTaskId());
        $this->assertNull($exception->getPrevious());
    }

    public function testExceptionIsRuntimeException(): void
    {
        $exception = new ExecutionException('Test message');

        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }
}
