<?php

declare(strict_types = 1);

namespace Lingoda\CronBundle\Tests;

use DateTimeInterface;
use Lingoda\CronBundle\Cron\CronJobInterface;

class TestJob implements CronJobInterface
{
    public ?DateTimeInterface $lastStartedAt = null;

    public function __construct(public bool $shouldRun = false, public ?string $schedule = null)
    {
    }

    public function run(?DateTimeInterface $lastStartedAt): void
    {
        $this->lastStartedAt = $lastStartedAt;
    }

    public function shouldRun(?DateTimeInterface $lastTriggeredAt = null): bool
    {
        return $this->shouldRun;
    }

    public function getLockTTL(): float
    {
        return 0.0;
    }

    public function __toString()
    {
        return (string) $this->schedule;
    }
}
