<?php

declare(strict_types=1);

use orange\framework\exceptions\http\Http400;
use orange\framework\exceptions\http\Http401;
use orange\framework\exceptions\http\Http403;
use orange\framework\exceptions\http\Http404;
use orange\framework\exceptions\http\Http422;
use orange\framework\exceptions\http\Http429;
use orange\framework\exceptions\http\Http500;
use orange\framework\exceptions\http\Http503;
use orange\framework\exceptions\http\Http301;

final class ErrorsTest extends \unitTestHelper
{
    protected function setUp(): void
    {
        // errors.php is included via the test bootstrap
    }

    public function testShow400ThrowsHttp400(): void
    {
        $this->expectException(Http400::class);
        show400('bad');
    }

    public function testShow401ThrowsHttp401(): void
    {
        $this->expectException(Http401::class);
        show401('unauthorized');
    }

    public function testShow403ThrowsHttp403(): void
    {
        $this->expectException(Http403::class);
        show403('forbidden');
    }

    public function testShow404ThrowsHttp404(): void
    {
        $this->expectException(Http404::class);
        show404('not found');
    }

    public function testShow422ThrowsHttp422(): void
    {
        $this->expectException(Http422::class);
        show422('unprocessable');
    }

    public function testShow429ThrowsHttp429(): void
    {
        $this->expectException(Http429::class);
        show429('too many');
    }

    public function testShow500ThrowsHttp500(): void
    {
        $this->expectException(Http500::class);
        show500('server error');
    }

    public function testShow503ThrowsHttp503(): void
    {
        $this->expectException(Http503::class);
        show503('unavailable');
    }

    public function testRedirect301ThrowsHttp301(): void
    {
        $this->expectException(Http301::class);

        $old = error_reporting();
        error_reporting($old & ~E_DEPRECATED);
        try {
            redirect301('https://example.com', 'moved');
        } finally {
            error_reporting($old);
        }
    }

    public function testErrorHandlerThrowsErrorException(): void
    {
        $this->expectException(ErrorException::class);

        $old = error_reporting();
        error_reporting(E_ALL);
        try {
            errorHandler(E_USER_WARNING, 'warning', __FILE__, __LINE__);
        } finally {
            error_reporting($old);
        }
    }

    public function testErrorHandlerIgnoresWhenReportingZero(): void
    {
        $old = error_reporting();
        error_reporting(0);
        $result = errorHandler(E_USER_WARNING, 'warning', __FILE__, __LINE__);
        error_reporting($old);

        $this->assertFalse($result);
    }
}
