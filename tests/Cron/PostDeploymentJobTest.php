<?php

declare(strict_types = 1);

namespace Lingoda\CronBundle\Tests\Cron;

use Carbon\CarbonImmutable;
use Lingoda\CronBundle\Cron\DueCronJobsTrigger;
use Lingoda\CronBundle\Cron\PostDeploymentJob;
use Lingoda\CronBundle\Entity\CronDates;
use Lingoda\CronBundle\Messenger\CronJobDueMessage;
use Lingoda\CronBundle\Repository\CronDatesRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class PostDeploymentJobTest extends TestCase
{
    /**
     * @var CronDatesRepository|MockObject
     */
    private $repository;

    /**
     * @var MockObject|MessageBusInterface
     */
    private $messageBus;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = $this->getMockBuilder(CronDatesRepository::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $messageBus = $this->getMockBuilder(MessageBusInterface::class)
            ->getMock()
        ;

        $this->repository = $repository;
        $this->messageBus = $messageBus;
    }

    public function testPostDeploymentJobRunsOnce(): void
    {
        $startTime = $this->createMock(CronDates::class);
        $startTime->expects(self::once())
            ->method('getLastTriggeredAt')
            ->willReturn(CarbonImmutable::now())
        ;

        $this->repository
            ->expects(self::exactly(2))
            ->method('find')
            ->willReturn(
                null,
                $startTime
            )
        ;

        $this->messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(
                self::callback(static fn (CronJobDueMessage $message) => $message->getCronJobId() === RunOnce::class)
            )
            ->willReturn(Envelope::wrap(self::returnArgument(0)))
        ;

        $cronJobTrigger = (new DueCronJobsTrigger(
            $this->repository,
            $this->messageBus,
            [new RunOnce()],
        ));

        $cronJobTrigger->trigger();
        $cronJobTrigger->trigger();
    }

    public function testIsDue(): void
    {
        $job = new RunOnce();

        self::assertTrue($job->shouldRun());
        self::assertFalse($job->shouldRun(CarbonImmutable::now()));
    }
}

/**
 * Dummy class representing PostDeploymentJob
 */
class RunOnce extends PostDeploymentJob
{
    public function execute(): void
    {
        // do nothing
    }
}
