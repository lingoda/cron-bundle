<?php

declare(strict_types = 1);

namespace Lingoda\CronBundle\Exception;

use Throwable;

class UnrecognizedCronJobException extends RuntimeException
{
    public function __construct(string $cronJobId, Throwable $previous = null)
    {
        parent::__construct("$cronJobId is not a recognized cron job instance", $previous);
    }
}
