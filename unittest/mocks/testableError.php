<?php

declare(strict_types=1);

use orange\framework\Error;

/**
 * Error::sendOutput() always ends with exit(), which would kill the test
 * process, and both __construct() and show() route through it. Overriding
 * just that one method records what would have been sent instead of actually
 * sending/exiting, while __construct()/show() - real Error.php logic - run
 * and get covered normally.
 */
class TestableError extends Error
{
    public array $sendOutputCalls = [];

    public function sendOutput(string $content, int $exitCode = 1): void
    {
        $this->sendOutputCalls[] = [$content, $exitCode];
    }
}

/**
 * A thrown exception exercising every optional Error-decoration hook
 * (getHttpCode/getOutput/decorate) that Error::__construct() looks for via
 * method_exists().
 */
class TestableDecoratingException extends \Exception
{
    public function getHttpCode(): int
    {
        return 404;
    }

    public function getOutput(): string
    {
        // deliberately empty so __construct()'s "no output content yet" check
        // still passes through to the viewFile branch set by decorate() below
        return '';
    }

    public function decorate(Error $error): void
    {
        $error->viewFile = 'errors/html/404';
    }
}
