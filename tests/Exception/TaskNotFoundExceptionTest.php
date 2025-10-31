<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\ClaudeTodoBundle\Exception\TaskNotFoundException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(TaskNotFoundException::class)]
final class TaskNotFoundExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionWithTaskId(): void
    {
        $exception = TaskNotFoundException::forId(123);

        $this->assertInstanceOf(TaskNotFoundException::class, $exception);
        $this->assertEquals('Task with ID 123 not found', $exception->getMessage());
        $this->assertEquals(123, $exception->getTaskId());
    }

    public function testExceptionWithGroupAndStatus(): void
    {
        $exception = TaskNotFoundException::forGroupAndStatus('backend', 'pending');

        $this->assertInstanceOf(TaskNotFoundException::class, $exception);
        $this->assertEquals('No pending task found in group "backend"', $exception->getMessage());
        $this->assertNull($exception->getTaskId());
    }

    public function testExceptionIsRuntimeException(): void
    {
        $exception = new TaskNotFoundException('Test message');

        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }
}
