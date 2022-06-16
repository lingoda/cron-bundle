<?php

declare(strict_types = 1);

namespace Lingoda\CronBundle\EventListener;

use Lingoda\CronBundle\Cron\CronJobInterface;
use Lingoda\CronBundle\Util\StringUtil;
use Psr\Container\ContainerInterface;
use ReflectionMethod;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Extends RunCronJobCommand input definition with invoked cron job setParameters method attributes as options.
 */
class RunCronJobInputDefinitionDecorator implements EventSubscriberInterface
{
    private ContainerInterface $cronJobLocator;

    public function __construct(ContainerInterface $cronJobLocator)
    {
        $this->cronJobLocator = $cronJobLocator;
    }

    public function onConsoleCommand(ConsoleCommandEvent $commandEvent): void
    {
        $input = $commandEvent->getInput();
        if ($input->getArgument('command') !== 'lg:cron:run-job') {
            return;
        }

        $command = $commandEvent->getCommand();
        if ($command instanceof HelpCommand) {
            // get the HelpCommand decorated command
            $application = $command->getApplication();
            if (!$application) {
                return;
            }

            $command = $application->find($input->getArgument('command'));
            /** @var string $cronJobId */
            $cronJobId = $input->getArgument('command_name');
        } else {
            /** @var string $cronJobId */
            $cronJobId = $input->getArgument('cron-job-id');
        }

        if (!$command || !$cronJobId) {
            return;
        }

        if (!$this->cronJobLocator->has($cronJobId)) {
            return;
        }

        $cronJob = $this->cronJobLocator->get($cronJobId);
        $this->injectCronParametersToHelp($cronJob, $command);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => ['onConsoleCommand', 10],
        ];
    }

    private function injectCronParametersToHelp(
        CronJobInterface $cronJob,
        Command $command
    ): void {
        try {
            $reflection = new ReflectionMethod($cronJob, 'setParameters');
        } catch (\ReflectionException $e) {
            return;
        }

        $cronJobClass = \get_class($cronJob);
        foreach ($reflection->getParameters() as $parameter) {
            $command->addOption(
                StringUtil::dashed($parameter->getName()),
                null,
                InputOption::VALUE_OPTIONAL,
                sprintf('Optional argument of "%s" Cron', $cronJobClass),
            );
        }
    }
}
