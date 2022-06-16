<?php

declare(strict_types = 1);

namespace Lingoda\CronBundle\Cron;

use DateTimeInterface;

/**
 * Base class for PostDeployment scripts that needs to be run once.
 */
abstract class PostDeploymentJob implements CronJobInterface
{
    public function run(?DateTimeInterface $lastStartedAt): void
    {
        if (null !== $lastStartedAt) {
            // presence of a start time indicates the job has already ran at least once
            return;
        }

        $this->execute();
    }

    abstract public function execute(): void;

    public function shouldRun(?DateTimeInterface $lastTriggeredAt = null): bool
    {
        return $lastTriggeredAt === null;
    }

    public function getLockTTL(): float
    {
        return self::DEFAULT_LOCK_TTL;
    }
}
