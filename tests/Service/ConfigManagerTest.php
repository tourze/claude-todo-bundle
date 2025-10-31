<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tourze\ClaudeTodoBundle\Service\ConfigManager;

/**
 * @internal
 */
#[CoversClass(ConfigManager::class)]
final class ConfigManagerTest extends TestCase
{
    private ConfigManager $configManager;

    private function createConfigManager(): void
    {
        // Get the class through reflection to avoid direct instantiation in integration test
        $reflectionClass = new \ReflectionClass(ConfigManager::class);
        $this->configManager = $reflectionClass->newInstance();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up environment variables after each test
        unset(
            $_ENV['CLAUDE_TODO_MODEL'],
            $_ENV['CLAUDE_TODO_CLI_PATH'],
            $_ENV['CLAUDE_TODO_MAX_ATTEMPTS'],
            $_ENV['CLAUDE_TODO_DEFAULT_PRIORITY'],
            $_ENV['CLAUDE_TODO_STOP_FILE'],
            $_ENV['CLAUDE_TODO_EXTRA_ARGS'],
            $_ENV['CLAUDE_TODO_WAIT_TIMEOUT'],
            $_ENV['CLAUDE_TODO_CHECK_INTERVAL'],
            $_ENV['CLAUDE_TODO_RETRY_DELAY'],
            $_ENV['CLAUDE_TODO_DEBUG']
        );
    }

    public function testGetClaudeModelReturnsDefault(): void
    {
        $this->createConfigManager();
        $model = $this->configManager->getClaudeModel();
        $this->assertEquals('claude-sonnet-4-20250514', $model);
    }

    public function testGetClaudeModelReturnsEnvironmentValue(): void
    {
        $this->createConfigManager();
        $_ENV['CLAUDE_TODO_MODEL'] = 'claude-opus-4-20250514';
        $model = $this->configManager->getClaudeModel();
        $this->assertEquals('claude-opus-4-20250514', $model);
    }

    public function testGetClaudePathReturnsDefault(): void
    {
        $this->createConfigManager();
        $path = $this->configManager->getClaudePath();
        $this->assertEquals('claude', $path);
    }

    public function testGetClaudePathReturnsEnvironmentValue(): void
    {
        $this->createConfigManager();
        $_ENV['CLAUDE_TODO_CLI_PATH'] = '/custom/path/to/claude';
        $path = $this->configManager->getClaudePath();
        $this->assertEquals('/custom/path/to/claude', $path);
    }

    public function testGetMaxAttemptsReturnsDefault(): void
    {
        $this->createConfigManager();
        $attempts = $this->configManager->getMaxAttempts();
        $this->assertEquals(10, $attempts);
    }

    public function testGetMaxAttemptsReturnsEnvironmentValue(): void
    {
        $this->createConfigManager();
        $_ENV['CLAUDE_TODO_MAX_ATTEMPTS'] = '5';
        $attempts = $this->configManager->getMaxAttempts();
        $this->assertEquals(5, $attempts);
    }

    public function testGetDefaultPriorityReturnsDefault(): void
    {
        $this->createConfigManager();
        $priority = $this->configManager->getDefaultPriority();
        $this->assertEquals('normal', $priority);
    }

    public function testGetDefaultPriorityReturnsEnvironmentValue(): void
    {
        $this->createConfigManager();
        $_ENV['CLAUDE_TODO_DEFAULT_PRIORITY'] = 'high';
        $priority = $this->configManager->getDefaultPriority();
        $this->assertEquals('high', $priority);
    }

    public function testGetStopFileReturnsDefault(): void
    {
        $this->createConfigManager();
        $stopFile = $this->configManager->getStopFile();
        $this->assertEquals('claude-runner.stop', $stopFile);
    }

    public function testGetStopFileReturnsEnvironmentValue(): void
    {
        $this->createConfigManager();
        $_ENV['CLAUDE_TODO_STOP_FILE'] = 'custom.stop';
        $stopFile = $this->configManager->getStopFile();
        $this->assertEquals('custom.stop', $stopFile);
    }

    public function testGetExtraArgsReturnsEmptyArrayByDefault(): void
    {
        $this->createConfigManager();
        $args = $this->configManager->getExtraArgs();
        $this->assertEquals([], $args);
    }

    public function testGetExtraArgsReturnsArrayFromEnvironment(): void
    {
        $this->createConfigManager();
        $_ENV['CLAUDE_TODO_EXTRA_ARGS'] = '--verbose --debug --max-retries 3';
        $args = $this->configManager->getExtraArgs();
        $this->assertEquals(['--verbose', '--debug', '--max-retries', '3'], $args);
    }

    public function testGetExtraArgsHandlesEmptyString(): void
    {
        $this->createConfigManager();
        $_ENV['CLAUDE_TODO_EXTRA_ARGS'] = '';
        $args = $this->configManager->getExtraArgs();
        $this->assertEquals([], $args);
    }

    public function testGetWaitTimeoutReturnsDefault(): void
    {
        $this->createConfigManager();
        $timeout = $this->configManager->getWaitTimeout();
        $this->assertEquals(300, $timeout);
    }

    public function testGetWaitTimeoutReturnsEnvironmentValue(): void
    {
        $this->createConfigManager();
        $_ENV['CLAUDE_TODO_WAIT_TIMEOUT'] = '600';
        $timeout = $this->configManager->getWaitTimeout();
        $this->assertEquals(600, $timeout);
    }

    public function testGetCheckIntervalReturnsDefault(): void
    {
        $this->createConfigManager();
        $interval = $this->configManager->getCheckInterval();
        $this->assertEquals(3, $interval);
    }

    public function testGetCheckIntervalReturnsEnvironmentValue(): void
    {
        $this->createConfigManager();
        $_ENV['CLAUDE_TODO_CHECK_INTERVAL'] = '5';
        $interval = $this->configManager->getCheckInterval();
        $this->assertEquals(5, $interval);
    }

    public function testGetRetryDelayReturnsDefault(): void
    {
        $this->createConfigManager();
        $delay = $this->configManager->getRetryDelay();
        $this->assertEquals(5, $delay);
    }

    public function testGetRetryDelayReturnsEnvironmentValue(): void
    {
        $this->createConfigManager();
        $_ENV['CLAUDE_TODO_RETRY_DELAY'] = '10';
        $delay = $this->configManager->getRetryDelay();
        $this->assertEquals(10, $delay);
    }

    public function testGetDebugModeReturnsFalseByDefault(): void
    {
        $this->createConfigManager();
        $debug = $this->configManager->getDebugMode();
        $this->assertFalse($debug);
    }

    #[DataProvider('debugModeProvider')]
    public function testGetDebugModeHandlesVariousValues(string $value, bool $expected): void
    {
        $this->createConfigManager();
        $_ENV['CLAUDE_TODO_DEBUG'] = $value;
        $debug = $this->configManager->getDebugMode();
        $this->assertEquals($expected, $debug);
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function debugModeProvider(): array
    {
        return [
            'true string' => ['true', true],
            'TRUE uppercase' => ['TRUE', true],
            '1 string' => ['1', true],
            'on string' => ['on', true],
            'yes string' => ['yes', true],
            'false string' => ['false', false],
            'FALSE uppercase' => ['FALSE', false],
            '0 string' => ['0', false],
            'off string' => ['off', false],
            'no string' => ['no', false],
            'empty string' => ['', false],
            'invalid string' => ['invalid', false],
        ];
    }

    public function testIntegerConversionHandlesInvalidValues(): void
    {
        $this->createConfigManager();
        $_ENV['CLAUDE_TODO_MAX_ATTEMPTS'] = 'not-a-number';
        $attempts = $this->configManager->getMaxAttempts();
        $this->assertEquals(0, $attempts);

        $_ENV['CLAUDE_TODO_WAIT_TIMEOUT'] = 'abc';
        $timeout = $this->configManager->getWaitTimeout();
        $this->assertEquals(0, $timeout);
    }

    public function testIntegerConversionHandlesFloatValues(): void
    {
        $this->createConfigManager();
        $_ENV['CLAUDE_TODO_CHECK_INTERVAL'] = '3.14';
        $interval = $this->configManager->getCheckInterval();
        $this->assertEquals(3, $interval);

        $_ENV['CLAUDE_TODO_RETRY_DELAY'] = '5.99';
        $delay = $this->configManager->getRetryDelay();
        $this->assertEquals(5, $delay);
    }
}
