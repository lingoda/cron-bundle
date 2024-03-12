<?php

declare(strict_types = 1);

namespace Lingoda\CronBundle\Cron;

use DateTimeInterface;

abstract class ScheduleBasedCronJob implements CronJobInterface
{
    public function shouldRun(?DateTimeInterface $lastTriggeredAt = null): bool
    {
        $schedule = $this->getSchedule();

        return $schedule->isDue($lastTriggeredAt);
    }

    public function getLockTTL(): float
    {
        return self::DEFAULT_LOCK_TTL;
    }

    public function __toString(): string
    {
        return (string) $this->getSchedule();
    }

    abstract public function getSchedule(): Schedule;
}
