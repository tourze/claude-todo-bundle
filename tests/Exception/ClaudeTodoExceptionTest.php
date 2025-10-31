<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\ClaudeTodoBundle\Exception\ClaudeTodoException;
use Tourze\ClaudeTodoBundle\Exception\ConfigurationException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(ClaudeTodoException::class)]
final class ClaudeTodoExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCanBeCreated(): void
    {
        $exception = ConfigurationException::missingRequired('test_key');
        $this->assertInstanceOf(ClaudeTodoException::class, $exception);
        $this->assertStringContainsString('test_key', $exception->getMessage());
    }

    public function testExceptionCanBeCreatedWithCode(): void
    {
        $exception = ConfigurationException::invalidValue('test_key', 'invalid', 'string');
        $this->assertEquals(0, $exception->getCode()); // ConfigurationException uses default code
        $this->assertInstanceOf(ClaudeTodoException::class, $exception);
    }

    public function testExceptionCanBeCreatedWithPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new ConfigurationException('Test message', 0, $previous);
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertInstanceOf(ClaudeTodoException::class, $exception);
    }
}
