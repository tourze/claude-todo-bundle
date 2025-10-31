<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\ValueObject;

final class ExecutionResult
{
    public function __construct(
        private readonly bool $success,
        private readonly string $output,
        private readonly ?string $errorOutput = null,
        private readonly ?int $exitCode = null,
        private readonly ?float $executionTime = null,
    ) {
    }

    public static function success(string $output, float $executionTime): self
    {
        return new self(
            success: true,
            output: $output,
            errorOutput: null,
            exitCode: 0,
            executionTime: $executionTime
        );
    }

    public static function failure(string $output, string $errorOutput, int $exitCode, float $executionTime): self
    {
        return new self(
            success: false,
            output: $output,
            errorOutput: $errorOutput,
            exitCode: $exitCode,
            executionTime: $executionTime
        );
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getOutput(): string
    {
        return $this->output;
    }

    public function getErrorOutput(): ?string
    {
        return $this->errorOutput;
    }

    public function getExitCode(): ?int
    {
        return $this->exitCode;
    }

    public function getExecutionTime(): ?float
    {
        return $this->executionTime;
    }

    public function getSummary(): string
    {
        if ($this->success) {
            return sprintf('Success (%.2fs)', $this->executionTime ?? 0);
        }

        return sprintf('Failed with exit code %d (%.2fs)', $this->exitCode ?? -1, $this->executionTime ?? 0);
    }
}
