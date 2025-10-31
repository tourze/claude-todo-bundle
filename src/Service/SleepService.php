<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Service;

class SleepService implements SleepServiceInterface
{
    public function sleep(int $seconds): void
    {
        sleep($seconds);
    }

    public function randomSleep(int $min = 1, int $max = 30): void
    {
        $randomDelay = random_int($min, $max);
        sleep($randomDelay);
    }
}
