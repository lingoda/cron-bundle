<?php

declare(strict_types = 1);

namespace Lingoda\CronBundle\Messenger;

use Lingoda\CronBundle\Cron\CronJobRunner;
use Lingoda\CronBundle\Exception\ConcurrentExecutionException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class CronJobDueHandler
{
    private CronJobRunner $cronJobRunner;

    public function __construct(CronJobRunner $cronJobRunner)
    {
        $this->cronJobRunner = $cronJobRunner;
    }

    public function __invoke(CronJobDueMessage $message): void
    {
        try {
            $this->cronJobRunner->run($message->getCronJobId());
        } catch (ConcurrentExecutionException $e) {
            throw new UnrecoverableMessageHandlingException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
