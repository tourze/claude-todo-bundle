<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\ClaudeTodoBundle\Enum\TaskPriority;
use Tourze\ClaudeTodoBundle\Enum\TaskStatus;
use Tourze\ClaudeTodoBundle\Exception\InvalidTaskTransitionException;
use Tourze\ClaudeTodoBundle\Repository\TodoTaskRepository;

#[ORM\Entity(repositoryClass: TodoTaskRepository::class)]
#[ORM\Table(name: 'claude_todo_tasks', options: ['comment' => 'Claude TODO任务表'])]
#[ORM\Index(name: 'claude_todo_tasks_idx_group_status', columns: ['group_name', 'status'])]
#[ORM\Index(name: 'claude_todo_tasks_idx_status_created', columns: ['status', 'created_time'])]
class TodoTask implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100, options: ['comment' => '任务分组名称'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $groupName;

    #[ORM\Column(type: Types::TEXT, options: ['comment' => '任务描述'])]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 65535)]
    private string $description;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: TaskStatus::class, options: ['comment' => '任务状态'])]
    #[Assert\Choice(callback: [TaskStatus::class, 'cases'])]
    private TaskStatus $status = TaskStatus::PENDING;

    #[ORM\Column(type: Types::STRING, length: 10, enumType: TaskPriority::class, options: ['comment' => '任务优先级'])]
    #[Assert\Choice(callback: [TaskPriority::class, 'cases'])]
    private TaskPriority $priority = TaskPriority::NORMAL;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, options: ['comment' => '创建时间'])]
    #[Assert\NotNull]
    #[Assert\Type(type: \DateTimeInterface::class)]
    private \DateTimeInterface $createdTime;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '更新时间'])]
    #[Assert\Type(type: \DateTimeInterface::class)]
    private ?\DateTimeInterface $updatedTime = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '执行时间'])]
    #[Assert\Type(type: \DateTimeInterface::class)]
    private ?\DateTimeInterface $executedTime = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '完成时间'])]
    #[Assert\Type(type: \DateTimeInterface::class)]
    private ?\DateTimeInterface $completedTime = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '执行结果'])]
    #[Assert\Length(max: 65535)]
    private ?string $result = null;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '乐观锁版本号'])]
    #[ORM\Version]
    private int $version = 1;

    public function __construct()
    {
        $this->createdTime = new \DateTimeImmutable();
        $this->status = TaskStatus::PENDING;
        $this->priority = TaskPriority::NORMAL;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGroupName(): string
    {
        return $this->groupName;
    }

    public function setGroupName(string $groupName): void
    {
        $this->groupName = $groupName;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getStatus(): TaskStatus
    {
        return $this->status;
    }

    public function setStatus(TaskStatus $status): void
    {
        if (!$this->status->canTransitionTo($status)) {
            throw new InvalidTaskTransitionException(sprintf('Cannot transition from %s to %s', $this->status->value, $status->value));
        }

        $this->status = $status;
        $this->updatedTime = new \DateTimeImmutable();
    }

    public function getPriority(): TaskPriority
    {
        return $this->priority;
    }

    public function setPriority(TaskPriority $priority): void
    {
        $this->priority = $priority;
    }

    public function getCreatedTime(): \DateTimeInterface
    {
        return $this->createdTime;
    }

    public function getUpdatedTime(): ?\DateTimeInterface
    {
        return $this->updatedTime;
    }

    public function getExecutedTime(): ?\DateTimeInterface
    {
        return $this->executedTime;
    }

    public function setExecutedTime(?\DateTimeInterface $executedTime): void
    {
        if ($executedTime instanceof \DateTime) {
            $executedTime = \DateTimeImmutable::createFromMutable($executedTime);
        }
        $this->executedTime = $executedTime;
    }

    public function getCompletedTime(): ?\DateTimeInterface
    {
        return $this->completedTime;
    }

    public function setCompletedTime(?\DateTimeInterface $completedTime): void
    {
        if ($completedTime instanceof \DateTime) {
            $completedTime = \DateTimeImmutable::createFromMutable($completedTime);
        }
        $this->completedTime = $completedTime;
    }

    public function complete(): self
    {
        $this->setStatus(TaskStatus::COMPLETED);
        $this->completedTime = new \DateTimeImmutable();

        return $this;
    }

    public function getResult(): ?string
    {
        return $this->result;
    }

    public function setResult(?string $result): void
    {
        $this->result = $result;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function __toString(): string
    {
        return sprintf('[%s] %s - %s', $this->groupName, $this->id ?? 'new', $this->status->value);
    }
}
