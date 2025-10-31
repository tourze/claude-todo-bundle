<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Exception;

/**
 * Claude AI 使用限制异常
 */
class UsageLimitException extends ClaudeTodoException
{
    private int $waitUntil;

    public static function withWaitTime(int $waitUntilTimestamp): self
    {
        $waitSeconds = max(0, $waitUntilTimestamp - time());
        $waitMinutes = (int) ceil($waitSeconds / 60);

        $exception = new self(sprintf(
            'Claude AI usage limit reached. Please wait %d minutes before retrying.',
            $waitMinutes
        ));
        $exception->waitUntil = $waitUntilTimestamp;

        return $exception;
    }

    public function getWaitUntil(): int
    {
        return $this->waitUntil;
    }

    public function getWaitSeconds(): int
    {
        return max(0, $this->waitUntil - time());
    }
}
