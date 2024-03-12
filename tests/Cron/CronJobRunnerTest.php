<?php

declare(strict_types = 1);

namespace Lingoda\CronBundle\Tests\Cron;

use DateTimeInterface;
use Generator;
use Lingoda\CronBundle\Cron\CronJobInterface;
use Lingoda\CronBundle\Cron\CronJobRunner;
use Lingoda\CronBundle\Cron\Schedule;
use Lingoda\CronBundle\Cron\ScheduleBasedCronJob;
use Lingoda\CronBundle\Entity\CronDates;
use Lingoda\CronBundle\Exception\ConcurrentExecutionException;
use Lingoda\CronBundle\Exception\RuntimeException;
use Lingoda\CronBundle\Exception\UnrecognizedCronJobException;
use Lingoda\CronBundle\Repository\CronDatesRepository;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;

class CronJobRunnerTest extends TestCase
{
    public function testItThrowsUnrecognizedCronJobException(): void
    {
        $this->expectException(UnrecognizedCronJobException::class);

        $cronJobId = 'My\Dummy\CronJob';

        $locator = $this->createMock(ContainerInterface::class);
        $locator
            ->method('has')
            ->willReturn(false)
        ;

        $lockFactory = $this->createMock(LockFactory::class);
        $repository = $this->createMock(CronDatesRepository::class);

        $runner = new CronJobRunner($locator, $lockFactory, $repository);
        $runner->run($cronJobId);
    }

    public function testItThrowsConcurrentExecutionException(): void
    {
        $this->expectException(ConcurrentExecutionException::class);

        $cronJob = $this->createCronJob();
        $cronJobId = \get_class($cronJob);

        $locator = $this->createMock(ContainerInterface::class);
        $locator
            ->method('has')
            ->willReturn(true)
        ;
        $locator
            ->method('get')
            ->willReturn($cronJob)
        ;

        $lock = $this->createMock(SharedLockInterface::class);
        $lock
            ->method('acquire')
            ->willReturn(false)
        ;

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->once())
            ->method('createLock')
            ->willReturn($lock)
        ;

        $repository = $this->createMock(CronDatesRepository::class);

        $runner = new CronJobRunner($locator, $lockFactory, $repository);
        $runner->run($cronJobId);
    }

    public function testItReleasesLock(): void
    {
        $cronJob = $this->createCronJob();
        $cronJobId = \get_class($cronJob);

        $locator = $this->createMock(ContainerInterface::class);
        $locator
            ->method('has')
            ->willReturn(true)
        ;
        $locator
            ->method('get')
            ->willReturn($cronJob)
        ;

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->method('acquire')
            ->willReturn(true)
        ;
        $lock
            ->expects($this->once())
            ->method('release')
        ;

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->method('createLock')
            ->willReturn($lock)
        ;

        $repository = $this->createMock(CronDatesRepository::class);
        $repository->expects($this->once())
            ->method('find')
            ->with($cronJobId)
            ->willReturn(null)
        ;
        $repository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(CronDates::class))
        ;

        $runner = new CronJobRunner($locator, $lockFactory, $repository);
        $runner->run($cronJobId);
    }

    public function testOptionalParameterInjection(): void
    {
        $cronJob = new TestCronWithParam();
        $cronJobId = \get_class($cronJob);

        $locator = $this->createMock(ContainerInterface::class);
        $locator->method('has')->willReturn(true);
        $locator->method('get')->willReturn($cronJob);

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lock->expects(self::exactly(2))->method('release');

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->method('createLock')->willReturn($lock);

        $repository = $this->createMock(CronDatesRepository::class);
        $repository->expects(self::exactly(2))->method('find')->with($cronJobId)->willReturn(null);
        $repository->expects(self::exactly(2))->method('save')->with(self::isInstanceOf(CronDates::class));

        $runner = new CronJobRunner($locator, $lockFactory, $repository);
        $runner->run($cronJobId);
        self::assertNull($cronJob->param1);

        $runner->run($cronJobId, ['param1' => 'custom']);
        self::assertSame('custom', $cronJob->param1);
    }

    /**
     * @dataProvider nonNullableParameterThrowsExceptionData
     */
    public function testNonNullableParameterThrowsException(ScheduleBasedCronJob $cronJob): void
    {
        $cronJobId = \get_class($cronJob);

        $locator = $this->createMock(ContainerInterface::class);
        $locator->method('has')->willReturn(true);
        $locator->method('get')->willReturn($cronJob);

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lock->expects(self::once())->method('release');

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->method('createLock')->willReturn($lock);

        $repository = $this->createMock(CronDatesRepository::class);
        $repository->expects(self::once())->method('find')->with($cronJobId)->willReturn(null);
        $repository->expects(self::never())->method('save')->with(self::isInstanceOf(CronDates::class));

        $runner = new CronJobRunner($locator, $lockFactory, $repository);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(sprintf(
            '"%s::setParameters()" all parameters should be nullable strings as they come from CLI arguments.',
            $cronJobId
        ));
        $runner->run($cronJobId, ['param1' => 'custom']);
    }

    /**
     * @return Generator<string, array{CronJobInterface}>
     */
    public function nonNullableParameterThrowsExceptionData(): Generator
    {
        yield 'parameters should be nullable' => [new TestCronWithNoNullableParam()];
        yield 'parameters should have type string' => [new TestCronWithInvalidTypeParam()];
    }

    private function createCronJob(bool $runsSuccessfully = true): CronJobInterface
    {
        return new class($runsSuccessfully) extends ScheduleBasedCronJob {
            private bool $runsSuccessfully;

            public function __construct(bool $runsSuccessfully = true)
            {
                $this->runsSuccessfully = $runsSuccessfully;
            }

            public function getSchedule(): Schedule
            {
                return Schedule::everyMinute();
            }

            public function run(?DateTimeInterface $lastStartedAt): void
            {
                if ($this->runsSuccessfully) {
                    return;
                }

                throw new RuntimeException();
            }
        };
    }
}

/**
 * @internal
 */
class TestCron extends ScheduleBasedCronJob
{
    public function getSchedule(): Schedule
    {
        return Schedule::everyMinute();
    }

    public function run(?DateTimeInterface $lastStartedAt): void
    {
    }
}

/**
 * @internal
 */
class TestCronWithNoNullableParam extends TestCron
{
    public function setParameters(string $param1): void
    {
    }
}

/**
 * @internal
 */
class TestCronWithInvalidTypeParam extends TestCron
{
    public function setParameters(?int $param1 = null): void
    {
    }
}

/**
 * @internal
 */
class TestCronWithParam extends TestCron
{
    public ?string $param1 = null;

    public function setParameters(?string $param1 = null): void
    {
        $this->param1 = $param1;
    }
}
