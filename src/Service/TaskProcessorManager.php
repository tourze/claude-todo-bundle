<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Service;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Tourze\ClaudeTodoBundle\Entity\TodoTask;
use Tourze\ClaudeTodoBundle\Processor\TaskProcessorInterface;
use Tourze\ClaudeTodoBundle\ValueObject\ProcessResult;

class TaskProcessorManager
{
    /**
     * @param iterable<TaskProcessorInterface> $processors
     */
    public function __construct(
        #[AutowireIterator(tag: 'claude_todo.task_processor', defaultPriorityMethod: 'getPriority')]
        private iterable $processors,
    ) {
    }

    public function process(TodoTask $task): ?ProcessResult
    {
        foreach ($this->processors as $processor) {
            if ($processor->supports($task)) {
                return $processor->process($task);
            }
        }

        return null;
    }

    public function hasProcessor(TodoTask $task): bool
    {
        foreach ($this->processors as $processor) {
            if ($processor->supports($task)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return TaskProcessorInterface[]
     */
    public function getProcessors(): array
    {
        return iterator_to_array($this->processors);
    }
}
