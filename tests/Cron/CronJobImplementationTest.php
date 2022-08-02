<?php

declare(strict_types = 1);

namespace Lingoda\CronBundle\Tests\Cron;

use Carbon\CarbonImmutable;
use DateTime;
use DateTimeInterface;
use Lingoda\CronBundle\Cron\Schedule;
use Lingoda\CronBundle\Cron\ScheduleBasedCronJob;
use PHPUnit\Framework\TestCase;

class CronJobImplementationTest extends TestCase
{
    public function testItShouldRun(): void
    {
        $c = new class() extends ScheduleBasedCronJob {
            public function getSchedule(): Schedule
            {
                return Schedule::everyMinute();
            }

            public function run(?DateTimeInterface $lastStartedAt): void
            {
            }
        };

        $this->assertTrue($c->shouldRun());
    }

    public function testItShouldRunIfPreviousRunDateMissed(): void
    {
        $c = new class() extends ScheduleBasedCronJob {
            public function getSchedule(): Schedule
            {
                $now = CarbonImmutable::now();
                $tenMinutesLater = $now->addMinutes(10);

                return Schedule::everyHour($tenMinutesLater->minute);
            }

            public function run(?DateTimeInterface $lastStartedAt): void
            {
            }
        };

        $this->assertTrue($c->shouldRun());
    }

    public function testItShouldNotRun(): void
    {
        $c = new class() extends ScheduleBasedCronJob {
            public function getSchedule(): Schedule
            {
                $now = CarbonImmutable::now();
                $tenMinutesLater = $now->addMinutes(10);

                return Schedule::everyHour($tenMinutesLater->minute);
            }

            public function run(?DateTimeInterface $lastStartedAt): void
            {
            }
        };

        $this->assertFalse($c->shouldRun(new DateTime()));
    }

    public function testItShouldNotRunTwice(): void
    {
        $c = new class() extends ScheduleBasedCronJob {
            public function getSchedule(): Schedule
            {
                return Schedule::everyMinute();
            }

            public function run(?DateTimeInterface $lastStartedAt): void
            {
            }
        };
        $this->assertFalse($c->shouldRun(new DateTime()));
    }
}
