<?php

declare(strict_types = 1);

namespace Lingoda\CronBundle\Tests\EventListener;

use DateTimeInterface;
use Generator;
use Lingoda\CronBundle\Cron\CronJobInterface;
use Lingoda\CronBundle\EventListener\RunCronJobInputDefinitionDecorator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Rule\InvokedCount;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;

final class RunCronJobInputDefinitionDecoratorTest extends TestCase
{
    private const COMMAND = 'lg:cron:run-job';
    /**
     * @var MockObject|ContainerInterface
     */
    private $mockCronJobLocator;
    private RunCronJobInputDefinitionDecorator $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockCronJobLocator = $this->createMock(ContainerInterface::class);
        $this->mockCronJobLocator
            ->method('has')
            ->willReturnMap([
                [null, false],
                [TestCronWithoutParams::class, true],
                [TestCronWithParams::class, true],
            ])
        ;

        $this->listener = new RunCronJobInputDefinitionDecorator($this->mockCronJobLocator);
    }

    public function testSubscribedEvents(): void
    {
        $expectedSubscriptions = [
            ConsoleEvents::COMMAND => ['onConsoleCommand', 10],
        ];

        self::assertSame($expectedSubscriptions, RunCronJobInputDefinitionDecorator::getSubscribedEvents());
    }

    public function testUnsupportedCommandsAreNotDecorated(): void
    {
        $command = $this->createCommand('custom-command');
        $command->expects(self::never())->method('addOption');

        $event = $this->createConsoleCommandEvent($command);

        /** @var MockObject|InputInterface $inputMock */
        $inputMock = $event->getInput();
        $inputMock
            ->expects(self::once())
            ->method('getArgument')
            ->with('command')
            ->willReturn($command->getName())
        ;

        $this->listener->onConsoleCommand($event);
    }

    public function testOriginalCommandFetchedFromHelpContext(): void
    {
        $command = $this->createCommand(self::COMMAND);
        $command->expects(self::never())->method('addOption');

        $application = $this->createMock(Application::class);
        $application->expects(self::once())->method('find')->with(self::COMMAND)->willReturn($command);

        $helpCommand = $this->createCommand(self::COMMAND, HelpCommand::class);
        $helpCommand->expects(self::once())->method('getApplication')->willReturn($application);
        $event = $this->createConsoleCommandEvent($helpCommand);

        /** @var MockObject|InputInterface $inputMock */
        $inputMock = $event->getInput();
        $inputMock
            ->method('getArgument')
            ->willReturnMap([
                ['command', self::COMMAND],
                ['command_name', null],
            ])
        ;

        $this->listener->onConsoleCommand($event);
    }

    /**
     * @dataProvider helpDecorationData
     */
    public function testHelpDecoration(string $cronHelp, InvokedCount $count): void
    {
        $command = $this->createCommand(self::COMMAND);
        $command->expects($count)->method('addOption');

        $application = $this->createMock(Application::class);
        $application->expects(self::once())->method('find')->with(self::COMMAND)->willReturn($command);

        $helpCommand = $this->createCommand(self::COMMAND, HelpCommand::class);
        $helpCommand->method('getApplication')->willReturn($application);
        $event = $this->createConsoleCommandEvent($helpCommand);

        /** @var MockObject|InputInterface $inputMock */
        $inputMock = $event->getInput();
        $inputMock
            ->method('getArgument')
            ->willReturnMap([
                ['command', self::COMMAND],
                ['command_name', $cronHelp],
            ])
        ;

        $this->mockCronJobLocator
            ->method('get')
            ->willReturnMap([
                [$cronHelp, new $cronHelp()],
            ])
        ;

        $this->listener->onConsoleCommand($event);
    }

    public function testRunningCronWithParamsExtendsCommandInputDefinition(): void
    {
        $command = $this->createCommand(self::COMMAND);
        $command->expects(self::exactly(2))
            ->method('addOption')
            ->withConsecutive(
                ['param1', null, InputOption::VALUE_OPTIONAL, sprintf('Optional argument of "%s" Cron', TestCronWithParams::class), null],
                ['param-with-camel-case', null, InputOption::VALUE_OPTIONAL, sprintf('Optional argument of "%s" Cron', TestCronWithParams::class), null]
            )
        ;

        $event = $this->createConsoleCommandEvent($command);

        /** @var MockObject|InputInterface $inputMock */
        $inputMock = $event->getInput();
        $inputMock
            ->method('getArgument')
            ->willReturnMap([
                ['command', self::COMMAND],
                ['cron-job-id', TestCronWithParams::class],
            ])
        ;

        $this->mockCronJobLocator
            ->method('get')
            ->willReturnMap([
                [TestCronWithParams::class, new TestCronWithParams()],
            ])
        ;

        $this->listener->onConsoleCommand($event);
    }

    /**
     * @return Generator<class-string<TestCronWithoutParams>, array{class-string<CronJobInterface>, InvokedCount}>
     */
    public function helpDecorationData(): Generator
    {
        // has no parameter so addOption should not be called
        yield TestCronWithoutParams::class => [TestCronWithoutParams::class, self::exactly(0)];

        // has 2 parameters so addOption should be called twice
        yield TestCronWithParams::class => [TestCronWithParams::class, self::exactly(2)];
    }

    private function createConsoleCommandEvent(Command $command): ConsoleCommandEvent
    {
        return new ConsoleCommandEvent($command, $this->createMock(InputInterface::class), new NullOutput());
    }

    /**
     * @param class-string<Command> $commandClass
     *
     * @return MockObject|Command
     */
    private function createCommand(string $commandName, string $commandClass = Command::class)
    {
        $command = $this->createMock($commandClass);
        $command->method('getName')->willReturn($commandName);

        return $command;
    }
}

/**
 * @internal
 */
class TestCronWithoutParams implements CronJobInterface
{
    public function run(?DateTimeInterface $lastStartedAt): void
    {
        // nothing...
    }

    public function shouldRun(?DateTimeInterface $lastTriggeredAt = null): bool
    {
        return true;
    }

    public function getLockTTL(): float
    {
        return 0;
    }
}

/**
 * @internal
 */
class TestCronWithParams extends TestCronWithoutParams
{
    public ?string $param1;
    public ?string $param2;

    public function setParameters(?string $param1 = null, ?string $paramWithCamelCase = null): void
    {
        $this->param1 = $param1;
        $this->param2 = $paramWithCamelCase;
    }
}
