<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\ValueObject;

use Tourze\ClaudeTodoBundle\Entity\TodoTask;

final class ProcessResult
{
    /**
     * @param array<string, mixed>|null $metadata
     */
    public function __construct(
        private readonly TodoTask $task,
        private readonly bool $success,
        private readonly string $message,
        private readonly ?array $metadata = null,
    ) {
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    public static function success(TodoTask $task, string $message, ?array $metadata = null): self
    {
        return new self(
            task: $task,
            success: true,
            message: $message,
            metadata: $metadata
        );
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    public static function failure(TodoTask $task, string $message, ?array $metadata = null): self
    {
        return new self(
            task: $task,
            success: false,
            message: $message,
            metadata: $metadata
        );
    }

    public function getTask(): TodoTask
    {
        return $this->task;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }
}
