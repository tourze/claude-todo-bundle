<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\ClaudeTodoBundle\Exception\InvalidTaskTransitionException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(InvalidTaskTransitionException::class)]
final class InvalidTaskTransitionExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCanBeCreated(): void
    {
        $exception = new InvalidTaskTransitionException('Test message');

        $this->assertInstanceOf(InvalidTaskTransitionException::class, $exception);
        $this->assertSame('Test message', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testExceptionCanBeCreatedWithCode(): void
    {
        $exception = new InvalidTaskTransitionException('Test message', 123);

        $this->assertSame('Test message', $exception->getMessage());
        $this->assertSame(123, $exception->getCode());
    }

    public function testExceptionCanBeCreatedWithPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new InvalidTaskTransitionException('Test message', 0, $previous);

        $this->assertSame('Test message', $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testExceptionIsRuntimeException(): void
    {
        $exception = new InvalidTaskTransitionException('Test message');

        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testExceptionForTransition(): void
    {
        $exception = new InvalidTaskTransitionException('Cannot transition from pending to completed');

        $this->assertSame('Cannot transition from pending to completed', $exception->getMessage());
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }
}
