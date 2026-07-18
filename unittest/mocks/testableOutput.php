<?php

declare(strict_types=1);

use orange\framework\Output;

/**
 * Output::phpExit()/phpHeader() wrap real language constructs (exit(), header())
 * that can't be exercised directly inside a running test process. Overriding
 * just those two low-level wrappers lets __construct()/forceHttps()/redirect()/
 * send() - all real Output.php logic - run and be covered normally, while
 * recording what would have happened instead of actually exiting/emitting headers.
 */
class TestableOutput extends Output
{
    public array $phpHeaderCalls = [];
    public array $phpExitCalls = [];

    protected function phpHeader(string $header, bool $replace = false): void
    {
        $this->phpHeaderCalls[] = [$header, $replace];
    }

    protected function phpExit(int $status = 0): void
    {
        $this->phpExitCalls[] = $status;
    }
}
