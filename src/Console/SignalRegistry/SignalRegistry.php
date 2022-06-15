<?php

declare(strict_types = 1);

namespace Lingoda\CronBundle\Console\SignalRegistry;

/**
 * THIS IS A DIRECT COPY OF \Symfony\Component\Console\SignalRegistry\SignalRegistry from Symfony 5.2
 *
 * @TODO remove when migrated to Symfony 5.2
 */
final class SignalRegistry
{
    /**
     * @var array<int, array<callable(int, bool): void>>
     */
    private array $signalHandlers = [];

    public function __construct()
    {
        if (\function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
        }
    }

    /**
     * @param callable(int, bool): void $signalHandler
     */
    public function register(int $signal, callable $signalHandler): void
    {
        if (!isset($this->signalHandlers[$signal])) {
            $previousCallback = pcntl_signal_get_handler($signal);

            if (\is_callable($previousCallback)) {
                $this->signalHandlers[$signal][] = $previousCallback;
            }
        }

        $this->signalHandlers[$signal][] = $signalHandler;

        pcntl_signal($signal, [$this, 'handle']);
    }

    public static function isSupported(): bool
    {
        if (!\function_exists('pcntl_signal')) {
            return false;
        }

        /** @var string $disabledFunctions */
        $disabledFunctions = ini_get('disable_functions');
        if (\in_array('pcntl_signal', explode(',', $disabledFunctions), true)) {
            return false;
        }

        return true;
    }

    /**
     * @internal
     */
    public function handle(int $signal): void
    {
        $count = \count($this->signalHandlers[$signal]);

        foreach ($this->signalHandlers[$signal] as $i => $signalHandler) {
            $hasNext = $i !== $count - 1;
            $signalHandler($signal, $hasNext);
        }
    }
}
