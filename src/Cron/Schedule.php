<?php

declare(strict_types = 1);

namespace Lingoda\CronBundle\Cron;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Cron\CronExpression;
use DateTimeInterface;
use Webmozart\Assert\Assert;

final class Schedule
{
    private CronExpression $cronExpression;

    private function __construct(CronExpression $expression)
    {
        $this->cronExpression = $expression;
    }

    public function __toString(): string
    {
        return (string) $this->cronExpression->getExpression();
    }

    public static function fromCronExpression(string $expression): self
    {
        return new self(new CronExpression($expression));
    }

    public static function everyMinute(): self
    {
        $expr = '* * * * *';

        return new self(new CronExpression($expr));
    }

    public static function everyHour(int $minute = 0): self
    {
        Assert::range($minute, 0, 59);
        $expr = "$minute * * * *";

        return new self(new CronExpression($expr));
    }

    public static function everyDay(int $hour = 0, int $minute = 0): self
    {
        Assert::range($hour, 0, 23);
        Assert::range($minute, 0, 59);

        $expr = "$minute $hour * * *";

        return new self(new CronExpression($expr));
    }

    public static function everyWeek(int $weekday = 1, int $hour = 0, int $minute = 0): self
    {
        Assert::range($weekday, 0, 7);
        Assert::range($hour, 0, 23);
        Assert::range($minute, 0, 59);

        $expr = "$minute $hour * * $weekday";

        return new self(new CronExpression($expr));
    }

    public static function everyMonth(int $monthday = 1, int $hour = 0, int $minute = 0): self
    {
        Assert::range($monthday, 1, 31);
        Assert::range($hour, 0, 23);
        Assert::range($minute, 0, 59);

        $expr = "$minute $hour $monthday * *";

        return new self(new CronExpression($expr));
    }

    public static function firstXDaysOfMonth(int $x = 1, int $hour = 0, int $minute = 0): self
    {
        Assert::range($x, 1, 28);
        Assert::range($hour, 0, 23);
        Assert::range($minute, 0, 59);

        $days = implode(',', range(1, $x));

        $expr = "$minute $hour $days * *";

        return new self(new CronExpression($expr));
    }

    public function isDue(?DateTimeInterface $lastTriggeredAt = null): bool
    {
        if (Carbon::now()->subMinute() <= $lastTriggeredAt) {
            // CronExpression expects to be called only once per minute
            return false;
        }

        // last start time is before previous run date, possibly a missed chance to run the cron (worker off?)
        if ($lastTriggeredAt < $this->cronExpression->getPreviousRunDate()) {
            return true;
        }

        return $this->cronExpression->isDue(CarbonImmutable::now());
    }
}
