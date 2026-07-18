<?php

declare(strict_types=1);

namespace orange\framework\stubs;

use Psr\Log\LoggerInterface;
use orange\framework\Log as FrameworkLog;
use orange\framework\interfaces\LogInterface;

class Log extends FrameworkLog implements LogInterface, LoggerInterface
{
    #[\Override]
    public function getThreshold(): int
    {
        // nothing logging
        return 0;
    }

    #[\Override]
    public function isEnabled(): bool
    {
        // we aren't logging
        return false;
    }

    #[\Override]
    public function write(string|int $level, string|\Stringable $message, array $context = []): void
    {
        // write nothing
    }
}
