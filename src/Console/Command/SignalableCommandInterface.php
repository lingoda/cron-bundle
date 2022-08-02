<?php

declare(strict_types = 1);

namespace Lingoda\CronBundle\Console\Command;

use Symfony\Component\Console\Command\SignalableCommandInterface as SymfonySignalableCommandInterfaceAlias;

if (interface_exists(SymfonySignalableCommandInterfaceAlias::class)) {
    interface SignalableCommandInterface extends SymfonySignalableCommandInterfaceAlias
    {
    }
} else {
    /**
     * Interface for command reacting to signal.
     */
    interface SignalableCommandInterface
    {
        /**
         * Returns the list of signals to subscribe.
         *
         * @return array<int>
         */
        public function getSubscribedSignals(): array;

        /**
         * The method will be called when the application is signaled.
         */
        public function handleSignal(int $signal): void;
    }
}
