<?php

declare(strict_types=1);

namespace orange\framework\interfaces;

/**
 * A severity-filtered log sink.
 *
 * The threshold is a bitmask, not a floor. Every level below is a distinct bit,
 * and isLevelEnabled() answers `threshold & level` - so a threshold names the
 * exact set of levels that get written, and levels you did not name are dropped
 * however severe they are:
 *
 *   changeThreshold(self::ALL);                        // everything
 *   changeThreshold(self::ERROR | self::CRITICAL);     // those two only
 *   changeThreshold(self::ERROR);                      // errors - NOT emergencies
 *   changeThreshold(self::NONE);                       // off, and isEnabled() is false
 *
 * That last distinction is the one to watch: ERROR is 8, so a threshold of
 * ERROR alone silences EMERGENCY, ALERT and CRITICAL. Ranges are spelled as
 * ORs, and ALL (255) is the union of the eight named bits.
 *
 * The levels themselves are the PSR-3 eight. The concrete service (orange\
 * framework\Log) implements PSR-3's LoggerInterface alongside this one and
 * forwards to a configured 'handler' - any PSR-3 logger - falling back to its
 * own file writer. This interface stays separate rather than extending PSR-3
 * because it is the half PSR-3 has no word for: write() takes an int bit rather
 * than a level string, and nothing in PSR-3 corresponds to the threshold.
 */
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
    /**
     * @param array<string, mixed> $context
     */
    public function write(int $level, string $message, array $context = []): void;
}
