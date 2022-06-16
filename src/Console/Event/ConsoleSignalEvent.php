<?php

declare(strict_types = 1);

namespace Lingoda\CronBundle\Console\Event;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * THIS IS A DIRECT COPY OF \Symfony\Component\Console\Event\ConsoleSignalEvent from Symfony 5.2
 *
 * @TODO remove when migrated to Symfony 5.2
 */
final class ConsoleSignalEvent extends ConsoleEvent
{
    private int $handlingSignal;

    public function __construct(Command $command, InputInterface $input, OutputInterface $output, int $handlingSignal)
    {
        parent::__construct($command, $input, $output);
        $this->handlingSignal = $handlingSignal;
    }

    public function getHandlingSignal(): int
    {
        return $this->handlingSignal;
    }
}
