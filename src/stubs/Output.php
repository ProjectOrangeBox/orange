<?php

declare(strict_types=1);

namespace orange\framework\stubs;

use orange\framework\Output as RealOutput;
use orange\framework\interfaces\OutputInterface;

/**
 * when you request this stub it will automatically load the config from the config
 * directory 1 level below it because that is what the parent class (orange\framework\Output) does.
 * Those config files include:
 *   mimes.php
 *   output.php
 *   statusCodes.php
 *
 * @package orange\framework\stubs
 */

class Output extends RealOutput implements OutputInterface
{
    // attached "output" from the php functions to a shared array
    // instead of sending it "out"
    // you can then read test if needed
    public array $test = [];

    protected function phpEcho(string $string): void
    {
        $this->test['echo'][] = $string;
    }

    protected function phpExit(int $status = 0): void
    {
        $this->test['exit'][] = $status;
    }

    protected function phpHeader(string $header, bool $replace = false): void
    {
        if ($replace) {
            foreach (array_keys($this->test['headers'] ?? []) as $key) {
                unset($this->test['headers'][$key]);
            }
        }

        $this->test['headers'][$header][] = $header;
    }
}
