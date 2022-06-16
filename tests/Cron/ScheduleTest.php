<?php

declare(strict_types = 1);

namespace Lingoda\CronBundle\Tests\Cron;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Lingoda\CronBundle\Cron\Schedule;
use PHPUnit\Framework\TestCase;

class ScheduleTest extends TestCase
{
    public function testItThrowsExceptionWhenCreatedWithAnInvalidCronExpression(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Schedule::fromCronExpression('invalid');
    }

    public function testItShouldBeDue(): void
    {
        $now = CarbonImmutable::now();

        $s = Schedule::everyMinute();
        $this->assertTrue($s->isDue());

        $s = Schedule::everyMinute();
        $this->assertTrue($s->isDue($now->subMinute()));

        $s = Schedule::everyHour($now->minute);
        $this->assertTrue($s->isDue());

        $s = Schedule::everyHour($now->minute);
        $this->assertTrue($s->isDue($now->subHour()));

        $s = Schedule::everyDay($now->hour, $now->minute);
        $this->assertTrue($s->isDue());

        $s = Schedule::everyDay($now->hour, $now->minute);
        $this->assertTrue($s->isDue($now->subDay()));

        $s = Schedule::everyWeek($now->dayOfWeek, $now->hour, $now->minute);
        $this->assertTrue($s->isDue());

        $s = Schedule::everyWeek($now->dayOfWeek, $now->hour, $now->minute);
        $this->assertTrue($s->isDue($now->subWeek()));

        $s = Schedule::everyMonth($now->day, $now->hour, $now->minute);
        $this->assertTrue($s->isDue());

        $s = Schedule::everyMonth($now->day, $now->hour, $now->minute);
        $this->assertTrue($s->isDue($now->subMonth()));

        $s = Schedule::everyHour($now->addMinutes(10)->minute);
        $this->assertTrue($s->isDue());

        $nextHour = $now->addHour();
        $s = Schedule::everyDay($nextHour->hour, $nextHour->minute);
        $this->assertTrue($s->isDue());
    }

    public function testItShouldNotBeDue(): void
    {
        $now = CarbonImmutable::now();
        $tenMinutesLater = $now->addMinutes(10);
        $nextDay = $now->addDay();
        $nextWeekday = $now->addWeekday();

        $s = Schedule::everyHour($tenMinutesLater->minute);
        $this->assertFalse($s->isDue($tenMinutesLater->subHour()));

        $s = Schedule::everyWeek($nextWeekday->dayOfWeek, $nextWeekday->hour, $nextWeekday->minute);
        $this->assertFalse($s->isDue($nextWeekday->subWeek()));

        $s = Schedule::everyMonth($nextDay->day, $nextDay->hour, $nextDay->minute);
        $this->assertFalse($s->isDue($nextDay->subMonth()));
    }

    public function testItShouldNotBeDueTwice(): void
    {
        $now = CarbonImmutable::now();

        $s = Schedule::everyMinute();
        $this->assertFalse($s->isDue($now));

        $s = Schedule::everyHour($now->minute);
        $this->assertFalse($s->isDue($now));

        $s = Schedule::everyDay($now->hour, $now->minute);
        $this->assertFalse($s->isDue($now));

        $s = Schedule::everyWeek($now->dayOfWeek, $now->hour, $now->minute);
        $this->assertFalse($s->isDue($now));

        $s = Schedule::everyMonth($now->day, $now->hour, $now->minute);
        $this->assertFalse($s->isDue($now));
    }

    public function testEveryMinute(): void
    {
        self::assertSame('* * * * *', (string) Schedule::everyMinute());
    }

    public function testEveryHour(): void
    {
        self::assertSame('12 * * * *', (string) Schedule::everyHour(12));
    }

    public function testEveryDay(): void
    {
        self::assertSame('16 12 * * *', (string) Schedule::everyDay(12, 16));
    }

    public function testEveryMonth(): void
    {
        self::assertSame('16 12 3 * *', (string) Schedule::everyMonth(3, 12, 16));
    }

    public function testFirstXDaysOfMonth(): void
    {
        CarbonImmutable::setTestNow('2021-03-01 10:45');

        self::assertSame('44 10 1,2,3 * *', (string) Schedule::firstXDaysOfMonth(3, 10, 44));
        self::assertSame('46 10 1,2,3 * *', (string) Schedule::firstXDaysOfMonth(3, 10, 46));
        self::assertSame('46 11 1,2,3 * *', (string) Schedule::firstXDaysOfMonth(3, 11, 46));
        self::assertSame('45 10 1,2,3 * *', (string) Schedule::firstXDaysOfMonth(3, 10, 45));

        CarbonImmutable::setTestNow();
    }
}
