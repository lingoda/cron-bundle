<?php

declare(strict_types = 1);

namespace Lingoda\CronBundle\Cron;

use DateTimeInterface;

interface CronJobInterface extends \Stringable
{
    public const DEFAULT_LOCK_TTL = 6 * 60 * 60 * 1.0;

    public function run(?DateTimeInterface $lastStartedAt): void;

    public function shouldRun(?DateTimeInterface $lastTriggeredAt = null): bool;

    public function getLockTTL(): float;
}
