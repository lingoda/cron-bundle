<?php

declare(strict_types = 1);

namespace Lingoda\CronBundle\Tests\Util;

use Generator;
use Lingoda\CronBundle\Util\StringUtil;
use PHPUnit\Framework\TestCase;

final class StringUtilTest extends TestCase
{
    /**
     * @dataProvider stringToDashData
     */
    public function testStringToDash(string $input, string $expected): void
    {
        self::assertSame($expected, StringUtil::dashed($input));
    }

    /**
     * @return Generator<array{string, string}>
     */
    public function stringToDashData(): Generator
    {
        yield ['user', 'user'];
        yield ['userName', 'user-name'];
        yield ['UserName', 'user-name'];
        yield ['UserName1', 'user-name1'];
    }
}
