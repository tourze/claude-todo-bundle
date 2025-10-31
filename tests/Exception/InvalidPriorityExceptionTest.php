<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\ClaudeTodoBundle\Exception\InvalidPriorityException;
use Tourze\ClaudeTodoBundle\Exception\TodoBundleExceptionInterface;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(InvalidPriorityException::class)]
final class InvalidPriorityExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionImplementsInterface(): void
    {
        $exception = new InvalidPriorityException('Invalid priority: unknown');

        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
        $this->assertInstanceOf(TodoBundleExceptionInterface::class, $exception);
    }

    public function testExceptionMessage(): void
    {
        $message = 'Invalid priority: unknown';
        $exception = new InvalidPriorityException($message);

        $this->assertSame($message, $exception->getMessage());
    }

    public function testExceptionCode(): void
    {
        $exception = new InvalidPriorityException('test', 123);

        $this->assertSame(123, $exception->getCode());
    }

    public function testExceptionPreviousThrowable(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new InvalidPriorityException('test', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }
}
