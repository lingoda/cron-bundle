<?php

declare(strict_types = 1);

namespace Lingoda\CronBundle\Command;

use Exception;
use Lingoda\CronBundle\Cron\CronJobInterface;
use Lingoda\CronBundle\Cron\CronJobRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Assert\Assert;

#[AsCommand(
    name: 'lg:cron:run-job',
    description: 'Run a single cron job',
)]
class RunCronJobCommand extends Command
{
    private const ARG_CRON_JOB_ID = 'cron-job-id';

    private CronJobRunner $cronJobRunner;
    private string $cronJobId;

    public function __construct(CronJobRunner $cronJobRunner)
    {
        parent::__construct();
        $this->cronJobRunner = $cronJobRunner;
    }

    protected function configure(): void
    {
        $this
            ->addArgument(self::ARG_CRON_JOB_ID, InputArgument::REQUIRED, 'The cron job id (its class name)')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getArgument(self::ARG_CRON_JOB_ID);
        $this->setCronJobId($id);

        $this->cronJobRunner->setLogger(new ConsoleLogger($output));

        try {
            $this->cronJobRunner->run($this->cronJobId, $input->getOptions());
        } catch (Exception $e) {
            throw new RuntimeException("$this->cronJobId cron job failed to run successfully", 0, $e);
        }

        return 0;
    }

    /**
     * @param string|string[]|null $id
     */
    private function setCronJobId(array|string|null $id): void
    {
        Assert::string($id);
        Assert::classExists($id);
        Assert::subclassOf($id, CronJobInterface::class);

        $this->cronJobId = ltrim($id, '\\');
    }
}
