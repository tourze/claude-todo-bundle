<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\ClaudeTodoBundle\ValueObject\ExecutionResult;

/**
 * @internal
 */
#[CoversClass(ExecutionResult::class)]
final class ExecutionResultTest extends TestCase
{
    public function testConstructorWithAllParameters(): void
    {
        $result = new ExecutionResult(
            success: true,
            output: 'test output',
            errorOutput: 'error output',
            exitCode: 0,
            executionTime: 1.5
        );

        $this->assertTrue($result->isSuccess());
        $this->assertSame('test output', $result->getOutput());
        $this->assertSame('error output', $result->getErrorOutput());
        $this->assertSame(0, $result->getExitCode());
        $this->assertSame(1.5, $result->getExecutionTime());
    }

    public function testConstructorWithMinimalParameters(): void
    {
        $result = new ExecutionResult(
            success: false,
            output: 'minimal output'
        );

        $this->assertFalse($result->isSuccess());
        $this->assertSame('minimal output', $result->getOutput());
        $this->assertNull($result->getErrorOutput());
        $this->assertNull($result->getExitCode());
        $this->assertNull($result->getExecutionTime());
    }

    public function testSuccessStaticFactory(): void
    {
        $result = ExecutionResult::success('Success output', 2.5);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('Success output', $result->getOutput());
        $this->assertNull($result->getErrorOutput());
        $this->assertSame(0, $result->getExitCode());
        $this->assertSame(2.5, $result->getExecutionTime());
    }

    public function testFailureStaticFactory(): void
    {
        $result = ExecutionResult::failure('Output', 'Error output', 1, 3.0);

        $this->assertFalse($result->isSuccess());
        $this->assertSame('Output', $result->getOutput());
        $this->assertSame('Error output', $result->getErrorOutput());
        $this->assertSame(1, $result->getExitCode());
        $this->assertSame(3.0, $result->getExecutionTime());
    }

    public function testGetSummaryForSuccess(): void
    {
        $result = ExecutionResult::success('output', 1.23);
        $this->assertSame('Success (1.23s)', $result->getSummary());
    }

    public function testGetSummaryForFailure(): void
    {
        $result = ExecutionResult::failure('output', 'error', 127, 4.56);
        $this->assertSame('Failed with exit code 127 (4.56s)', $result->getSummary());
    }

    public function testGetSummaryWithNullExecutionTime(): void
    {
        $successResult = new ExecutionResult(success: true, output: 'output');
        $this->assertSame('Success (0.00s)', $successResult->getSummary());

        $failureResult = new ExecutionResult(success: false, output: 'output');
        $this->assertSame('Failed with exit code -1 (0.00s)', $failureResult->getSummary());
    }

    public function testImmutability(): void
    {
        $result = ExecutionResult::success('original output', 1.0);

        // 尝试获取所有属性，确保对象状态不会改变
        $isSuccess = $result->isSuccess();
        $output = $result->getOutput();
        $errorOutput = $result->getErrorOutput();
        $exitCode = $result->getExitCode();
        $executionTime = $result->getExecutionTime();
        $summary = $result->getSummary();

        // 再次验证所有值都保持不变
        $this->assertTrue($result->isSuccess());
        $this->assertSame('original output', $result->getOutput());
        $this->assertNull($result->getErrorOutput());
        $this->assertSame(0, $result->getExitCode());
        $this->assertSame(1.0, $result->getExecutionTime());
    }

    public function testEmptyOutput(): void
    {
        $result = new ExecutionResult(
            success: true,
            output: ''
        );

        $this->assertSame('', $result->getOutput());
        $this->assertTrue($result->isSuccess());
    }

    public function testLargeOutput(): void
    {
        $largeOutput = str_repeat('A', 10000);
        $result = new ExecutionResult(
            success: true,
            output: $largeOutput
        );

        $this->assertSame($largeOutput, $result->getOutput());
        $this->assertSame(10000, strlen($result->getOutput()));
    }

    public function testSpecialCharactersInOutput(): void
    {
        $specialChars = "Line1\nLine2\tTabbed\r\nWindows line\0Null char";
        $result = new ExecutionResult(
            success: true,
            output: $specialChars,
            errorOutput: $specialChars
        );

        $this->assertSame($specialChars, $result->getOutput());
        $this->assertSame($specialChars, $result->getErrorOutput());
    }

    public function testNegativeExitCode(): void
    {
        $result = new ExecutionResult(
            success: false,
            output: 'output',
            errorOutput: 'error',
            exitCode: -1
        );

        $this->assertSame(-1, $result->getExitCode());
        $this->assertSame('Failed with exit code -1 (0.00s)', $result->getSummary());
    }

    public function testZeroExecutionTime(): void
    {
        $result = ExecutionResult::success('instant', 0.0);

        $this->assertSame(0.0, $result->getExecutionTime());
        $this->assertSame('Success (0.00s)', $result->getSummary());
    }

    public function testVeryLongExecutionTime(): void
    {
        $result = ExecutionResult::failure('timeout', 'timeout error', 124, 9999.99);

        $this->assertSame(9999.99, $result->getExecutionTime());
        $this->assertSame('Failed with exit code 124 (9999.99s)', $result->getSummary());
    }

    public function testUnicodeInOutput(): void
    {
        $unicodeOutput = '测试中文 🚀 émojis ñ special chars';
        $result = new ExecutionResult(
            success: true,
            output: $unicodeOutput
        );

        $this->assertSame($unicodeOutput, $result->getOutput());
    }
}
