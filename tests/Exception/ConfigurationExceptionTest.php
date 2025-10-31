<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\ClaudeTodoBundle\Exception\ConfigurationException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(ConfigurationException::class)]
final class ConfigurationExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCanBeCreated(): void
    {
        $exception = new ConfigurationException('Configuration error');
        $this->assertInstanceOf(ConfigurationException::class, $exception);
        $this->assertEquals('Configuration error', $exception->getMessage());
    }

    public function testExceptionWithInvalidParameter(): void
    {
        $exception = new ConfigurationException('Invalid parameter: CLAUDE_TODO_MODEL');
        $this->assertStringContainsString('CLAUDE_TODO_MODEL', $exception->getMessage());
    }
}
