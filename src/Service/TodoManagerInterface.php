<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Service;

use Tourze\ClaudeTodoBundle\Entity\TodoTask;
use Tourze\ClaudeTodoBundle\Enum\TaskPriority;
use Tourze\ClaudeTodoBundle\Enum\TaskStatus;
use Tourze\ClaudeTodoBundle\Exception\TaskNotFoundException;

interface TodoManagerInterface
{
    /**
     * 创建新的TODO任务
     *
     * @param string $groupName   任务分组名称
     * @param string $description 任务描述
     * @param TaskPriority|string $priority    优先级
     *
     * @return TodoTask 创建的任务实例
     *
     * @throws \InvalidArgumentException 当参数无效时
     */
    public function push(string $groupName, string $description, TaskPriority|string $priority = TaskPriority::NORMAL): TodoTask;

    /**
     * 获取下一个可执行的任务
     *
     * @param string|null $groupName 可选的分组过滤
     *
     * @return TodoTask|null 可执行的任务，没有时返回null
     *
     * @throws \RuntimeException 当数据库操作失败时
     */
    public function pop(?string $groupName = null): ?TodoTask;

    /**
     * 根据ID获取任务
     *
     * @param int $id 任务ID
     *
     * @return TodoTask 任务实例
     *
     * @throws TaskNotFoundException 当任务不存在时
     */
    public function getTask(int $id): TodoTask;

    /**
     * 更新任务状态
     *
     * @param TodoTask    $task   要更新的任务
     * @param TaskStatus  $status 新状态
     * @param string|null $result 执行结果（可选）
     *
     * @throws \InvalidArgumentException 当状态无效时
     */
    public function updateTaskStatus(TodoTask $task, TaskStatus $status, ?string $result = null): void;
}
