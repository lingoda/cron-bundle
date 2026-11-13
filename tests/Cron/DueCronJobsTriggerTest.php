<?php

declare(strict_types = 1);

namespace Lingoda\CronBundle\Tests\Cron;

use Carbon\CarbonImmutable;
use Lingoda\CronBundle\Cron\CronJobInterface;
use Lingoda\CronBundle\Cron\DueCronJobsTrigger;
use Lingoda\CronBundle\Entity\CronDates;
use Lingoda\CronBundle\Messenger\CronJobDueMessage;
use Lingoda\CronBundle\Repository\CronDatesRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class DueCronJobsTriggerTest extends TestCase
{
    /**
     * @var CronDatesRepository&MockObject
     */
    private $repository;

    /**
     * @var MockObject&MessageBusInterface
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

    public function testItTriggersJobs(): void
    {
        $cronJob1 = $this->getMockBuilder(CronJobInterface::class)
            ->getMock()
        ;
        $cronJob2 = $this->getMockBuilder(CronJobInterface::class)
            ->getMock()
        ;
        $cronJob3 = $this->getMockBuilder(CronJobInterface::class)
            ->getMock()
        ;

        $cronJob1->method('shouldRun')->willReturn(true);
        $cronJob2->method('shouldRun')->willReturn(false);
        $cronJob3->method('shouldRun')->willReturn(true);

        $this->repository->expects(self::once())->method('findAllIndexedById')->willReturn([]);
        $this->repository->expects(self::exactly(3))->method('find')->willReturn(null);
        $this->messageBus
            ->expects(self::exactly(2))
            ->method('dispatch')
            ->with(self::callback(function ($arg) use ($cronJob1): bool {
                static $counter = 0;

                $messages = [
                    new CronJobDueMessage(get_class($cronJob1)),
                    new CronJobDueMessage('cron_job3_service_id'),
                ];

                $this->assertInstanceOf(CronJobDueMessage::class, $arg);
                $this->assertEquals($messages[$counter++]->getCronJobId(), $arg->getCronJobId());

                return true;
            }))
            ->willReturnCallback(static function () use ($cronJob1): Envelope {
                static $counter = 0;

                $messages = [
                    new CronJobDueMessage(get_class($cronJob1)),
                    new CronJobDueMessage('cron_job3_service_id'),
                ];

                return Envelope::wrap($messages[$counter++]);
            })
        ;

        (new DueCronJobsTrigger(
            $this->repository,
            $this->messageBus,
            [
                get_class($cronJob1) => $cronJob1,
                'cron_job2_service_id' => $cronJob2,
                'cron_job3_service_id' => $cronJob3,
            ]
        ))->trigger();
    }

    public function testItUsesTheStoredTriggerDateAndUpdatesTheExistingRecord(): void
    {
        $lastTriggeredAt = new CarbonImmutable('2025-01-01 10:00:00');
        $record = new CronDates('due_job', $lastTriggeredAt);

        $dueJob = $this->createMock(CronJobInterface::class);
        $dueJob->expects(self::once())->method('shouldRun')->with($lastTriggeredAt)->willReturn(true);
        $newJob = $this->createMock(CronJobInterface::class);
        $newJob->expects(self::once())->method('shouldRun')->with(null)->willReturn(false);

        $this->repository->expects(self::once())->method('findAllIndexedById')->willReturn(['due_job' => $record]);
        $this->repository->expects(self::once())->method('save')->with(self::identicalTo($record));
        $this->messageBus->expects(self::once())->method('dispatch')->willReturn(Envelope::wrap(new CronJobDueMessage('due_job')));

        (new DueCronJobsTrigger(
            $this->repository,
            $this->messageBus,
            ['due_job' => $dueJob, 'new_job' => $newJob]
        ))->trigger();

        self::assertGreaterThan($lastTriggeredAt, $record->getLastTriggeredAt());
    }

    public function testItUpdatesTheRowTheDatabaseMatchesWhenTheIdDiffersInCase(): void
    {
        $record = new CronDates('App\\FooJob', new CarbonImmutable('2025-01-01 10:00:00'));

        $job = $this->createMock(CronJobInterface::class);
        $job->method('shouldRun')->willReturn(true);

        $this->repository->method('findAllIndexedById')->willReturn(['App\\FooJob' => $record]);
        $this->repository->expects(self::once())->method('find')->with('App\\FOOJob')->willReturn($record);
        $this->repository->expects(self::once())->method('save')->with(self::identicalTo($record));
        $this->messageBus->method('dispatch')->willReturn(Envelope::wrap(new CronJobDueMessage('App\\FOOJob')));

        (new DueCronJobsTrigger($this->repository, $this->messageBus, ['App\\FOOJob' => $job]))->trigger();
    }
}
