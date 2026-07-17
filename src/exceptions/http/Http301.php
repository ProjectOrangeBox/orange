<?php

declare(strict_types=1);

namespace orange\framework\exceptions\http;

use Throwable;
use orange\framework\Error;

class Http301 extends Http
{
    protected string $url;

    public function __construct(string $url, string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        $this->url = $url;

        // $code defaults to 0 so the Http base derives the status from the class
        // name (Http301 => 301, Http302 => 302, etc.); pass an explicit code to override.
        parent::__construct($message, $code, $previous);
    }

    public function decorate(Error $error): void
    {
        $error->output->header('Location: ' . $this->url);

        parent::decorate($error);
    }
}
