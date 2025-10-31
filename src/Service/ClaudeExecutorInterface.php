<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Service;

use Tourze\ClaudeTodoBundle\Entity\TodoTask;
use Tourze\ClaudeTodoBundle\Exception\ExecutionException;
use Tourze\ClaudeTodoBundle\ValueObject\ExecutionResult;

interface ClaudeExecutorInterface
{
    /**
     * 执行任务
     *
     * @param TodoTask $task    要执行的任务
     * @param array<string, mixed> $options 额外选项 (model, maxAttempts等)
     *
     * @return ExecutionResult 执行结果
     *
     * @throws ExecutionException 当执行失败时
     */
    public function execute(TodoTask $task, array $options = []): ExecutionResult;

    /**
     * 检查Claude CLI是否可用
     *
     * @return bool
     */
    public function isAvailable(): bool;
}
