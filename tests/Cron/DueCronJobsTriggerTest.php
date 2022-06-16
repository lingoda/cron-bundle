<?php

declare(strict_types = 1);

namespace Lingoda\CronBundle\Tests\Cron;

use Lingoda\CronBundle\Cron\CronJobInterface;
use Lingoda\CronBundle\Cron\DueCronJobsTrigger;
use Lingoda\CronBundle\Messenger\CronJobDueMessage;
use Lingoda\CronBundle\Repository\CronDatesRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class DueCronJobsTriggerTest extends TestCase
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

        $this->repository->method('find')->willReturn(null);
        $this->messageBus
            ->expects(self::exactly(2))
            ->method('dispatch')
            ->with(self::callback(function ($arg) use ($cronJob1, $cronJob3): bool {
                static $counter = 0;

                $messages = [
                    CronJobDueMessage::createFromCronJobInstance($cronJob1),
                    CronJobDueMessage::createFromCronJobInstance($cronJob3),
                ];

                $this->assertInstanceOf(CronJobDueMessage::class, $arg);
                $this->assertEquals($messages[$counter++]->getCronJobId(), $arg->getCronJobId());

                return true;
            }))
            ->willReturnCallback(static function () use ($cronJob1, $cronJob3): Envelope {
                static $counter = 0;

                $messages = [
                    CronJobDueMessage::createFromCronJobInstance($cronJob1),
                    CronJobDueMessage::createFromCronJobInstance($cronJob3),
                ];

                return Envelope::wrap($messages[$counter++]);
            })
        ;

        (new DueCronJobsTrigger(
            $this->repository,
            $this->messageBus,
            [$cronJob1, $cronJob2, $cronJob3]
        ))->trigger();
    }
}
