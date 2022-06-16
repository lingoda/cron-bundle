<?php

declare(strict_types = 1);

namespace Lingoda\CronBundle\Util;

final class StringUtil
{
    public static function dashed(string $string): string
    {
        return mb_strtolower((string) preg_replace(['/([A-Z]+)([A-Z][a-z])/', '/([a-z\d])([A-Z])/'], '\1-\2', $string));
    }
}
