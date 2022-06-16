<?php
declare(strict_types=1);

namespace Lingoda\CronBundle\Exception;

use Throwable;

class RuntimeException extends \RuntimeException implements ExceptionInterface
{
    public function __construct(string $message = "", Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
