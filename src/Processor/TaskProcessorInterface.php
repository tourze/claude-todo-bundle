<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Processor;

use Tourze\ClaudeTodoBundle\Entity\TodoTask;
use Tourze\ClaudeTodoBundle\ValueObject\ProcessResult;

interface TaskProcessorInterface
{
    /**
     * 处理任务
     *
     * @param TodoTask $task 要处理的任务
     *
     * @return ProcessResult 处理结果
     */
    public function process(TodoTask $task): ProcessResult;

    /**
     * 检查是否支持该任务
     *
     * @param TodoTask $task
     *
     * @return bool
     */
    public function supports(TodoTask $task): bool;
}
