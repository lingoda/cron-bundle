<?php

declare(strict_types = 1);

namespace Lingoda\CronBundle\Command;

use Lingoda\CronBundle\Console\Command\SignalableCommandInterface;
use Lingoda\CronBundle\Cron\DueCronJobsTrigger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Assert\Assert;

class TriggerDueCronJobsCommand extends Command implements SignalableCommandInterface
{
    use LockableTrait;

    private const SLEEP_SECONDS = 60;

    protected static $defaultName = 'lg:cron:trigger-due-jobs';

    private DueCronJobsTrigger $dueCronJobsTrigger;
    private bool $shouldStop = false;

    public function __construct(DueCronJobsTrigger $dueCronJobsTrigger)
    {
        parent::__construct();
        $this->dueCronJobsTrigger = $dueCronJobsTrigger;
    }

    /**
     * @return array<int>
     */
    public function getSubscribedSignals(): array
    {
        return [\SIGTERM];
    }

    public function handleSignal(int $signal): void
    {
        if (\SIGTERM === $signal) {
            $this->shouldStop = true;
            $this->dueCronJobsTrigger->stop();
        }
    }

    protected function configure(): void
    {
        $this
            ->addOption('time-limit', null, InputOption::VALUE_OPTIONAL, 'Stop the worker when the given time limit (in seconds) is reached')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->dueCronJobsTrigger->setLogger(new ConsoleLogger($output));

        if (!$this->lock()) {
            $output->writeln('You shall not run multiple cron workers.');

            return 1;
        }

        $timeLimitSeconds = $input->getOption('time-limit');
        Assert::nullOrIntegerish($timeLimitSeconds);
        Assert::nullOrGreaterThan($timeLimitSeconds, 0);
        $endTime = time() + $timeLimitSeconds;

        while (!$timeLimitSeconds || time() < $endTime) {
            $this->dueCronJobsTrigger->trigger();

            if ($this->shouldStop) {
                return 0;
            }

            sleep(self::SLEEP_SECONDS);
        }

        return 0;
    }
}
