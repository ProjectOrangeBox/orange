<?php

declare(strict_types=1);

namespace orange\framework\interfaces;

interface LogInterface
{
    public const NONE = 0;
    public const EMERGENCY  = 1;
    public const ALERT = 2;
    public const CRITICAL = 4;
    public const ERROR = 8;
    public const WARNING = 16;
    public const NOTICE = 32;
    public const INFO = 64;
    public const DEBUG = 128;
    public const ALL = 255;

    public function changeThreshold(int $threshold): self;
    public function getThreshold(): int;
    public function isEnabled(): bool;
    public function isLevelEnabled(string|int $level): bool;
    public function write(int $level, string $message, array $context = []): void;
}
