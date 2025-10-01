<?php

declare(strict_types = 1);

namespace Lingoda\CronBundle\Tests\Command;

use ArrayIterator;
use Carbon\CarbonImmutable;
use Lingoda\CronBundle\Command\ListCronJobsCommand;
use Lingoda\CronBundle\Entity\CronDates;
use Lingoda\CronBundle\Repository\CronDatesRepository;
use Lingoda\CronBundle\Tests\TestJob;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ListCronJobsCommandTest extends TestCase
{
    public function testExecute(): void
    {
        $cronDatesRepository = $this->createMock(CronDatesRepository::class);

        $job1 = new TestJob(true);
        $job2 = new TestJob(true);
        $job3 = new TestJob(false);

        $jobs = new ArrayIterator([
            TestJob::class => $job1,
            'test_job_2' => $job2,
            'test_job_3' => $job3,
        ]);

        $command = new ListCronJobsCommand($jobs, $cronDatesRepository);

        $tester = new CommandTester($command);

        $cronDatesRepository->expects($this->exactly(3))->method('find')
            ->with(self::callback(function ($arg): bool {
                static $counter = 0;
                $expected = [TestJob::class, 'test_job_2', 'test_job_3'];

                $this->assertEquals($expected[$counter++], $arg);

                return true;
            }))
            ->willReturnCallback(static function () {
                static $counter = 0;

                $expected = [
                    new CronDates(TestJob::class, new CarbonImmutable('2025-01-01')),
                    new CronDates('test_job_2', new CarbonImmutable('2025-01-02')),
                    new CronDates('test_job_3', new CarbonImmutable('2025-01-03')),
                ];

                return $expected[$counter++];
            });

        $tester->execute([]);

        self::assertStringEqualsFile(__DIR__ . '/expected/list_cron_command_display.txt', $tester->getDisplay());
    }
}
