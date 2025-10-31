<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\ClaudeTodoBundle\Exception\InvalidTaskStateException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(InvalidTaskStateException::class)]
final class InvalidTaskStateExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionForStateTransition(): void
    {
        $exception = InvalidTaskStateException::forStateTransition(123, 'completed', 'pending');

        $this->assertInstanceOf(InvalidTaskStateException::class, $exception);
        $this->assertEquals(
            'Cannot transition task 123 from state "completed" to "pending"',
            $exception->getMessage()
        );
    }

    public function testExceptionForInvalidState(): void
    {
        $exception = InvalidTaskStateException::forInvalidState('unknown');

        $this->assertInstanceOf(InvalidTaskStateException::class, $exception);
        $this->assertStringContainsString('Invalid task state "unknown"', $exception->getMessage());
        $this->assertStringContainsString('pending, in_progress, completed, failed', $exception->getMessage());
    }
}
