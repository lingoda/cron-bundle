<?php

declare(strict_types = 1);

namespace Lingoda\CronBundle\Console;

use Lingoda\CronBundle\Console\Command\SignalableCommandInterface;
use Lingoda\CronBundle\Console\Event\ConsoleSignalEvent;
use Lingoda\CronBundle\Console\SignalRegistry\SignalRegistry;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputAwareInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * THIS IS A PARTIAL COPY OF \Symfony\Component\Console\Application from Symfony 5.2
 *
 * @TODO remove when migrated to Symfony 5.2
 */
final class SignalableApplication extends Application
{
    public const SIGNAL = 'console.signal';

    private SignalRegistry $signalRegistry;

    /**
     * @var array<int>
     */
    private array $signalsToDispatchEvent = [];

    public function __construct(KernelInterface $kernel)
    {
        parent::__construct($kernel);

        if (\defined('SIGINT') && SignalRegistry::isSupported()) {
            $this->signalRegistry = new SignalRegistry();
            $this->signalsToDispatchEvent = [SIGINT, SIGTERM, SIGUSR1, SIGUSR2];
        }
    }

    protected function doRunCommand(Command $command, InputInterface $input, OutputInterface $output): int
    {
        /** @var HelperSet $helperSet */
        $helperSet = $command->getHelperSet();
        foreach ($helperSet as $helper) {
            if ($helper instanceof InputAwareInterface) {
                $helper->setInput($input);
            }
        }

        if ($command instanceof SignalableCommandInterface) {
            if (!isset($this->signalRegistry)) {
                throw new RuntimeException('Unable to subscribe to signal events. Make sure that the `pcntl` extension is installed and that "pcntl_*" functions are not disabled by your php.ini\'s "disable_functions" directive.');
            }

            /** @var EventDispatcherInterface|null $dispatcher */
            $dispatcher = $this->getKernel()->getContainer()->get('event_dispatcher');
            if ($dispatcher) {
                foreach ($this->signalsToDispatchEvent as $signal) {
                    $event = new ConsoleSignalEvent($command, $input, $output, $signal);

                    $this->signalRegistry->register($signal, function ($signal, $hasNext) use ($event, $dispatcher): void {
                        $dispatcher->dispatch($event, self::SIGNAL);

                        // No more handlers, we try to simulate PHP default behavior
                        if (!$hasNext) {
                            if (!\in_array($signal, [SIGUSR1, SIGUSR2], true)) {
                                exit(0);
                            }
                        }
                    });
                }
            }

            foreach ($command->getSubscribedSignals() as $signal) {
                $this->signalRegistry->register($signal, [$command, 'handleSignal']);
            }
        }

        return parent::doRunCommand($command, $input, $output);
    }
}
