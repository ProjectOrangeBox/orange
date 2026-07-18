<?php

declare(strict_types=1);

namespace orange\framework;

use Throwable;
use Psr\Log\LoggerInterface;
use orange\framework\base\Singleton;
use orange\framework\exceptions\InvalidValue;
use orange\framework\interfaces\LogInterface;
use orange\framework\traits\ConfigurationTrait;
use orange\framework\exceptions\IncorrectInterface;
use orange\framework\exceptions\filesystem\DirectoryNotWritable;

/**
 * Overview of Log.php
 *
 * This file defines the Log class in the orange\framework namespace.
 * It implements both the framework’s LogInterface and the PSR-3 LoggerInterface,
 * meaning it can be used as a standard logger in PHP applications while also supporting Orange’s internal requirements.
 * It extends Singleton, so only one instance exists during runtime.
 *
 * ⸻
 *
 * 1. Core Purpose
 *  •   Provides a centralized logging system for the framework and applications.
 *  •   Supports PSR-3 compatibility so developers can use familiar logging methods (info(), error(), etc.).
 *  •   Allows either a custom logging handler (any PSR-3 logger) or its own file-based logger.
 *  •   Manages logging thresholds, so only messages at or above a certain severity are written.
 *
 * ⸻
 *
 * 2. Configuration & Setup
 *  •   Uses ConfigurationTrait to merge settings.
 *  •   Key configuration options:
 *  •   threshold → bitmask determining which log levels are active.
 *  •   handler → optional PSR-3 compatible logger.
 *  •   filepath → path to the log file (if no custom handler).
 *  •   timestamp format, line format, permissions → control log output formatting.
 *  •   During construction:
 *  •   Initializes thresholds and level mappings.
 *  •   If handler is provided, validates it implements LoggerInterface.
 *  •   Otherwise, defaults to file-based logging and ensures the log directory is writable.
 *
 * ⸻
 *
 * 3. Threshold & Enable/Disable
 *  •   changeThreshold($threshold) → adjusts logging threshold and enables/disables logging accordingly.
 *  •   getThreshold() / isEnabled() → retrieve current state.
 *  •   Logging only occurs if enabled = true and the given log level is within the threshold.
 *
 * ⸻
 *
 * 4. Logging Methods
 *
 * Implements all PSR-3 standard methods, which internally call log():
 *  •   emergency(), alert(), critical(), error(),
 *  •   warning(), notice(), info(), debug().
 *
 * Each delegates to log($level, $message, $context).
 *
 * ⸻
 *
 * 5. Logging Execution
 *  •   log():
 *  •   Checks if level is enabled.
 *  •   Converts level between string ↔ int (convert2()).
 *  •   If using a custom handler → forwards the log to it.
 *  •   If using the internal handler → writes the message to a file.
 *  •   write():
 *  •   Builds a log entry line using placeholders:
 *  •   %timestamp, %level, %message, %context.
 *  •   Appends to log file, creating it with correct permissions if missing.
 *
 * ⸻
 *
 * 6. Level Handling
 *  •   Maintains mappings between:
 *  •   String levels (DEBUG, ERROR, etc.).
 *  •   Integer constants (bitmask style).
 *  •   convert2() ensures valid conversion and throws InvalidValue for unknown levels.
 *
 * ⸻
 *
 * 7. File Safety
 *  •   isFileWritable():
 *  •   Ensures the directory for the log file exists (creates if needed).
 *  •   Confirms directory is writable.
 *  •   Throws DirectoryNotWritable if not possible.
 *
 * ⸻
 *
 * 8. Big Picture
 *  •   Log.php is the logging backbone of the Orange framework.
 *  •   It allows developers to log messages in a standard, PSR-3 compatible way.
 *  •   Provides flexibility: use Orange’s built-in file logging or inject a custom PSR-3 logger.
 *  •   Ensures log safety, configurability, and consistent formatting.
 *
 * @package orange\framework
 */
class Log extends Singleton implements LogInterface, LoggerInterface
{
    /** include ConfigurationTrait methods */
    use ConfigurationTrait;

    /**
     * Logging handler instance (PSR-3 compatible or internal handler).
     */
    protected $handler;

    /**
     * Determines whether logging is enabled.
     */
    protected bool $enabled = false;

    /**
     * Logging threshold level.
     */
    protected int $threshold = 0;

    /**
     * Mapping of PSR logging levels to their integer representations.
     *
     * @var array<string, int>
     */
    protected array $psrLevels = [
        'NONE'      => self::NONE,
        'EMERGENCY' => self::EMERGENCY,
        'ALERT'     => self::ALERT,
        'CRITICAL'  => self::CRITICAL,
        'ERROR'     => self::ERROR,
        'WARNING'   => self::WARNING,
        'NOTICE'    => self::NOTICE,
        'INFO'      => self::INFO,
        'DEBUG'     => self::DEBUG,
    ];

    /**
     * Reverse mapping of integer levels to PSR logging level names.
     *
     * @var array<int, string>
     */
    protected array $psrLevelsInt = [];

    /**
     * Constructor is protected to enforce the singleton pattern.
     *
     * @param array $config Configuration data.
     * @throws InvalidValue If the handler is not an object.
     * @throws IncorrectInterface If the handler does not implement LoggerInterface.
     * @throws DirectoryNotWritable If the log file directory is not writable.
     */
    protected function __construct(array $config)
    {
        $this->config = $this->mergeConfigWith($config);

        // default off
        $this->enabled = false;

        $this->psrLevelsInt = array_flip($this->psrLevels);

        $this->changeThreshold($this->config['threshold']);

        if (isset($this->config['handler'])) {
            if (!is_object($this->config['handler'])) {
                throw new InvalidValue('handler is not an object');
            }

            if (!$this->config['handler'] instanceof LoggerInterface) {
                throw new IncorrectInterface('handler is not an instance of LoggerInterface');
            }

            $this->handler = $this->config['handler'];
        } else {
            // isFileWritable() already ran for this filepath via changeThreshold() above
            $this->handler = $this;
        }
    }

    /**
     * Changes the logging threshold.
     *
     * @param int $threshold Logging threshold level.
     * @return self
     */
    public function changeThreshold(int $threshold): self
    {
        logMsg('INFO', __METHOD__ . ' ' . $threshold);

        $this->threshold = $threshold;

        $this->enabled = $this->threshold !== 0;

        // only the internal file-based handler needs a writable log directory; a custom
        // PSR-3 handler manages its own storage and shouldn't require 'filepath' to exist
        if (!isset($this->config['handler'])) {
            $this->isFileWritable($this->config['filepath']);
        }

        return $this;
    }

    /**
     * Retrieves the current logging threshold.
     *
     * @return int
     */
    public function getThreshold(): int
    {
        return $this->threshold;
    }

    /**
     * Checks if logging is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Writes a log entry to the specified handler or file.
     *
     * @param string|int $level Log level.
     * @param string|\Stringable $message Log message.
     * @param array $context Contextual information.
     */
    public function write(string|int $level, string|\Stringable $message, array $context = []): void
    {
        if ($this->isLevelEnabled($level)) {
            $contextString = !empty($context) ? var_export($context, true) : '';

            $data = str_replace(
                ['%timestamp', '%level', '%message', '%context'],
                [date($this->config['timestamp format']), strtoupper((string) $this->convert2($level, 'string')), $message, $contextString],
                $this->config['line format']
            );

            $isNewFile = !file_exists($this->config['filepath']);

            file_put_contents($this->config['filepath'], $data, FILE_APPEND | LOCK_EX);

            if ($isNewFile) {
                chmod($this->config['filepath'], $this->config['permissions']);
            }
        }
    }

    /* PSR-3 methods */

    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->log(self::EMERGENCY, $message, $context);
    }

    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->log(self::ALERT, $message, $context);
    }

    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->log(self::CRITICAL, $message, $context);
    }

    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }

    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->log(self::WARNING, $message, $context);
    }

    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->log(self::NOTICE, $message, $context);
    }

    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->log(self::INFO, $message, $context);
    }

    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->log(self::DEBUG, $message, $context);
    }

    /* match PSR-3 LoggerInterface */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if ($this->isLevelEnabled($level)) {
            $levelAsString = $this->convert2($level, 'string');

            if ($this->handler == $this) {
                $this->write($levelAsString, $message, $context);
            } else {
                $this->handler->$levelAsString($message, $context);
            }
        }
    }

    /**
     * Checks if a log level is enabled based on the threshold.
     *
     * @param string|int $level Log level.
     * @return bool
     */
    protected function isLevelEnabled(string|int $level): bool
    {
        return $this->enabled && ($this->threshold & $this->convert2($level, 'int'));
    }

    /**
     * Converts a log level between integer and string representation.
     *
     * @param string|int $input Log level.
     * @param string $as Desired return type ('int' or 'string').
     * @return mixed
     * @throws InvalidValue If the level is invalid.
     */
    protected function convert2(int|string $input, string $as): mixed
    {
        if (is_string($input)) {
            if (!isset($this->psrLevels[strtoupper($input)])) {
                throw new InvalidValue('Unknown message log level "' . $input . '".');
            }
            $method = mb_strtolower($input);
        } else {
            if (!isset($this->psrLevelsInt[$input])) {
                throw new InvalidValue('Unknown message log level "' . $input . '".');
            }
            $method = $this->psrLevelsInt[$input];
        }

        return ($as == 'int') ? $this->psrLevels[strtoupper($method)] : $method;
    }

    /**
     * Checks if the log file is writable.
     * This method ensures the directory exists and is writable,
     * and creates it if necessary.
     * Throws DirectoryNotWritable if the directory cannot be created or is not writable.
     *
     * @param string $file
     * @return bool
     * @throws DirectoryNotWritable
     */
    protected function isFileWritable(string $file): bool
    {
        // check we can write in the directory
        if ($this->enabled) {
            $dir = dirname($file);

            if (!file_exists($dir)) {
                try {
                    mkdir($dir, 0777, true);
                } catch (Throwable) {
                    throw new DirectoryNotWritable($dir);
                }
            }

            if (!is_writable($dir)) {
                throw new DirectoryNotWritable($dir);
            }
        }

        return true;
    }
}
