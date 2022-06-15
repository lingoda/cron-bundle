<?php

declare(strict_types = 1);

namespace Lingoda\CronBundle\Cron;

use Carbon\Carbon;
use Lingoda\CronBundle\Entity\CronDates;
use Lingoda\CronBundle\Exception\ConcurrentExecutionException;
use Lingoda\CronBundle\Exception\UnrecognizedCronJobException;
use Lingoda\CronBundle\Repository\CronDatesRepository;
use Lingoda\CronBundle\Util\StringUtil;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use ReflectionNamedType;
use Symfony\Component\Lock\LockFactory;

class CronJobRunner implements LoggerAwareInterface
{
    private ContainerInterface $cronJobLocator;
    private LockFactory $lockFactory;
    private CronDatesRepository $cronDatesRepo;
    private ?LoggerInterface $logger;

    public function __construct(
        ContainerInterface $cronJobLocator,
        LockFactory $cronBundleLockFactory,
        CronDatesRepository $cronDatesRepo
    ) {
        $this->cronJobLocator = $cronJobLocator;
        $this->lockFactory = $cronBundleLockFactory;
        $this->cronDatesRepo = $cronDatesRepo;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function run(string $cronJobId, array $parameters = []): void
    {
        if (!$this->cronJobLocator->has($cronJobId)) {
            throw new UnrecognizedCronJobException($cronJobId);
        }

        /** @var CronJobInterface $cronjob */
        $cronjob = $this->cronJobLocator->get($cronJobId);
        $lock = $this->lockFactory->createLock($cronJobId, $cronjob->getLockTTL());

        if (!$lock->acquire()) {
            throw new ConcurrentExecutionException('Could not acquire lock for ' . $cronJobId);
        }

        try {
            $this->logCronJobAboutToRun($cronJobId);

            $record = $this->cronDatesRepo->find($cronJobId);
            $lastStartedAt = $record ? $record->getLastStartedAt() : null;

            $this->setParameters($cronjob, $parameters);

            $this->recordStartTime($cronJobId, $record);

            $cronjob->run($lastStartedAt);

            $this->logCronJobFinished($cronJobId);
        } finally {
            $lock->release();
        }
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    private function recordStartTime(string $cronJobId, ?CronDates $cronDates): void
    {
        $newStartTime = Carbon::now();

        if (null === $cronDates) {
            $cronDates = new CronDates($cronJobId, $newStartTime);
        }

        $cronDates->setLastStartedAt($newStartTime);

        $this->cronDatesRepo->save($cronDates);
    }

    private function logCronJobAboutToRun(string $cronJobId): void
    {
        if (isset($this->logger)) {
            $context = ['cron' => $cronJobId, 'time' => Carbon::now()];
            $this->logger->info('Running {cron} cron job at {time}', $context);
        }
    }

    private function logCronJobFinished(string $cronJobId): void
    {
        if (isset($this->logger)) {
            $context = ['cron' => $cronJobId, 'time' => Carbon::now()];
            $this->logger->info('{cron} finished at {time}', $context);
        }
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function setParameters(CronJobInterface $cronJob, array $parameters = []): void
    {
        if (empty($parameters) || !method_exists($cronJob, 'setParameters')) {
            return;
        }

        try {
            $reflectionMethod = new ReflectionMethod($cronJob, 'setParameters');
        } catch (\ReflectionException $e) {
            return;
        }

        $args = [];
        foreach ($reflectionMethod->getParameters() as $parameter) {
            /** @var ReflectionNamedType|null $reflectionType */
            $reflectionType = $parameter->getType();
            if ($reflectionType === null || $reflectionType->getName() !== 'string' || !$reflectionType->allowsNull()) {
                throw new \RuntimeException(sprintf('"%s::setParameters()" all parameters should be nullable strings as they come from CLI arguments.', \get_class($cronJob)));
            }

            $paramName = $parameter->getName();
            $args[] = $parameters[StringUtil::dashed($paramName)] ?? $parameter->getDefaultValue();
        }

        \call_user_func_array([$cronJob, 'setParameters'], $args);
    }
}
