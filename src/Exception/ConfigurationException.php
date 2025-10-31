<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Exception;

/**
 * 配置异常
 */
class ConfigurationException extends ClaudeTodoException
{
    public static function missingRequired(string $key): self
    {
        return new self(sprintf(
            'Required configuration "%s" is missing. Please set it in your environment variables.',
            $key
        ));
    }

    public static function invalidValue(string $key, string $value, string $expected): self
    {
        return new self(sprintf(
            'Invalid value "%s" for configuration "%s". Expected: %s',
            $value,
            $key,
            $expected
        ));
    }
}
