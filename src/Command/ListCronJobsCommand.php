<?php

declare(strict_types = 1);

namespace Lingoda\CronBundle\Command;

use Cron\CronExpression;
use Lingoda\CronBundle\Cron\CronJobInterface;
use Lingoda\CronBundle\Repository\CronDatesRepository;
use Lorisleiva\CronTranslator\CronParsingException;
use Lorisleiva\CronTranslator\CronTranslator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'lg:cron:list',
    description: 'List cron jobs ordered by cron expression\'s next run date',
)]
class ListCronJobsCommand extends Command
{
    private const FORMAT = 'Y-m-d H:i:s \U\T\C';

    /**
     * @param iterable<int, CronJobInterface> $cronJobs
     */
    public function __construct(
        private readonly iterable $cronJobs,
        private readonly CronDatesRepository $cronDatesRepository,
    ) {
        parent::__construct();
    }

    /**
     * @throws CronParsingException|\Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $table = new Table($output);
        $table->setHeaders(['Cron Job Id', 'Schedule', 'Last Triggered At', 'Next Trigger At']);

        /**
         * @var array<int, array{
         *     id: class-string,
         *     schedule: string|null,
         *     last_triggered_at: \DateTimeInterface|null,
         *     next_trigger_at: \DateTimeInterface|null
         * }> $cronJobs
         */
        $cronJobs = [];
        foreach ($this->cronJobs as $cronJob) {
            $cronJobId = \get_class($cronJob);
            $record = $this->cronDatesRepository->find($cronJobId);
            $lastTriggeredAt = $record ? $record->getLastTriggeredAt() : null;
            $cronExpression = (string) $cronJob;
            $schedule = !empty($cronExpression) ? $cronExpression : null;
            $nextTriggerAt = $schedule ? (new CronExpression($schedule))->getNextRunDate() : null;
            $cronJobs[] = [
                'id' => $cronJobId,
                'schedule' => $schedule,
                'last_triggered_at' => $lastTriggeredAt,
                'next_trigger_at' => $nextTriggerAt,
            ];
        }

        usort($cronJobs, function($a, $b) {
            $nextTriggerAtA = $a['next_trigger_at'];
            $nextTriggerAtB = $b['next_trigger_at'];

            if ($nextTriggerAtA == $nextTriggerAtB) {
                return 0;
            }

            return $nextTriggerAtA < $nextTriggerAtB ? -1 : 1;
        });

        foreach ($cronJobs as $cronJob) {
            $schedule = $cronJob['schedule'];
            $lastTriggeredAt = $cronJob['last_triggered_at'];
            $nextTriggerAt = $cronJob['next_trigger_at'];
            $table->addRow([
                $cronJob['id'],
                $schedule ? CronTranslator::translate($schedule) : '-',
                $lastTriggeredAt ? $lastTriggeredAt->format(self::FORMAT) : '-',
                $nextTriggerAt ? $nextTriggerAt->format(self::FORMAT) : '-',
            ]);
        }
        $table->render();

        return Command::SUCCESS;
    }
}
