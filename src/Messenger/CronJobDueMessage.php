<?php

declare(strict_types = 1);

namespace Lingoda\CronBundle\Messenger;

use Lingoda\CronBundle\Cron\CronJobInterface;

class CronJobDueMessage
{
    private string $cronJobId;

    /**
     * @param string $cronJobId
     */
    public function __construct(string $cronJobId)
    {
        $this->cronJobId = $cronJobId;
    }

    public static function createFromCronJobInstance(CronJobInterface $cronJob): self
    {
        return new self(\get_class($cronJob));
    }

    public function getCronJobId(): string
    {
        return $this->cronJobId;
    }
}
