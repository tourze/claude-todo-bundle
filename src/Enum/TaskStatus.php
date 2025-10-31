<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Enum;

use Tourze\EnumExtra\BadgeInterface;
use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

enum TaskStatus: string implements Itemable, Labelable, Selectable, BadgeInterface
{
    use ItemTrait;
    use SelectTrait;

    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED => 'Completed',
            self::FAILED => 'Failed',
        };
    }

    public function isFinished(): bool
    {
        return match ($this) {
            self::COMPLETED, self::FAILED => true,
            default => false,
        };
    }

    public function canTransitionTo(self $newStatus): bool
    {
        return match ($this) {
            self::PENDING => in_array($newStatus, [self::IN_PROGRESS, self::FAILED], true),
            self::IN_PROGRESS => in_array($newStatus, [self::COMPLETED, self::FAILED], true),
            self::COMPLETED, self::FAILED => false,
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING => 'fg=yellow',
            self::IN_PROGRESS => 'fg=cyan',
            self::COMPLETED => 'fg=green',
            self::FAILED => 'fg=white;bg=red',
        };
    }

    public function getColoredLabel(): string
    {
        return sprintf('<%s>%s</>', $this->getColor(), $this->getLabel());
    }

    public function getColoredChineseLabel(): string
    {
        $label = match ($this) {
            self::PENDING => '待处理',
            self::IN_PROGRESS => '进行中',
            self::COMPLETED => '已完成',
            self::FAILED => '失败',
        };

        return sprintf('<%s>%s</>', $this->getColor(), $label);
    }

    public function getBadge(): string
    {
        return match ($this) {
            self::PENDING => self::WARNING,
            self::IN_PROGRESS => self::INFO,
            self::COMPLETED => self::SUCCESS,
            self::FAILED => self::DANGER,
        };
    }
}
