<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Service;

interface SleepServiceInterface
{
    public function sleep(int $seconds): void;

    public function randomSleep(int $min = 1, int $max = 30): void;
}
