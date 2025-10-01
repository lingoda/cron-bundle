<?php

declare(strict_types = 1);

namespace Lingoda\CronBundle\Cron;

use Carbon\Carbon;
use Lingoda\CronBundle\Entity\CronDates;
use Lingoda\CronBundle\Messenger\CronJobDueMessage;
use Lingoda\CronBundle\Repository\CronDatesRepository;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class DueCronJobsTrigger implements LoggerAwareInterface
{
    private CronDatesRepository $cronDatesRepo;
    private MessageBusInterface $messageBus;

    /**
     * @var iterable|CronJobInterface[]
     */
    private iterable $cronJobs;

    private ?LoggerInterface $logger;
    private bool $shouldStop = false;

    /**
     * @param iterable<string, CronJobInterface> $cronJobs
     * @phpstan-param iterable<CronJobInterface> $cronJobs
     */
    public function __construct(
        CronDatesRepository $cronDatesRepo,
        MessageBusInterface $messageBus,
        iterable $cronJobs
    ) {
        $this->cronDatesRepo = $cronDatesRepo;
        $this->messageBus = $messageBus;
        $this->cronJobs = $cronJobs;
    }

    public function stop(): void
    {
        $this->shouldStop = true;
    }

    public function trigger(): void
    {
        foreach ($this->cronJobs as $cronJobId => $cronJob) {
            $record = $this->cronDatesRepo->find($cronJobId);
            $lastTriggeredAt = $record ? $record->getLastTriggeredAt() : null;
            if (!$cronJob->shouldRun($lastTriggeredAt)) {
                continue;
            }

            $this->recordTriggerTime($cronJobId, $record);

            $this->messageBus->dispatch(new CronJobDueMessage($cronJobId));
            $this->logCronJobWasTriggered($cronJobId);

            if ($this->shouldStop) {
                return;
            }
        }
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    private function recordTriggerTime(string $cronJobId, ?CronDates $cronDates): void
    {
        $triggeredAt = Carbon::now();

        if (null === $cronDates) {
            $cronDates = new CronDates($cronJobId, $triggeredAt);
        } else {
            $cronDates->setLastTriggeredAt($triggeredAt);
        }

        $this->cronDatesRepo->save($cronDates);
    }

    private function logCronJobWasTriggered(string $cronJobId): void
    {
        if (isset($this->logger)) {
            $this->logger->info('Triggered {cron} cron job', ['cron' => $cronJobId]);
        }
    }
}
