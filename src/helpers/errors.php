<?php

declare(strict_types=1);

/*
 * Wrapper to throw http exceptions for common http errors
 */

// 400 Bad Request
if (!function_exists('show400')) {
    function show400(string $message = ''): void
    {
        throw new \orange\framework\exceptions\http\Http400($message);
    }
}

// 401 Unauthorized
if (!function_exists('show401')) {
    function show401(string $message = ''): void
    {
        throw new \orange\framework\exceptions\http\Http401($message);
    }
}

// 403 Forbidden
if (!function_exists('show403')) {
    function show403(string $message = ''): void
    {
        throw new \orange\framework\exceptions\http\Http403($message);
    }
}

// 404 Not Found
if (!function_exists('show404')) {
    function show404(string $message = ''): void
    {
        throw new \orange\framework\exceptions\http\Http404($message);
    }
}

// 422 Unprocessable Entity
if (!function_exists('show422')) {
    function show422(string $message = ''): void
    {
        throw new \orange\framework\exceptions\http\Http422($message);
    }
}

// 429 Too Many Requests
if (!function_exists('show429')) {
    function show429(string $message = ''): void
    {
        throw new \orange\framework\exceptions\http\Http429($message);
    }
}

// 500 Internal Server Error
if (!function_exists('show500')) {
    function show500(string $message = ''): void
    {
        throw new \orange\framework\exceptions\http\Http500($message);
    }
}

// 503 Service Unavailable
if (!function_exists('show503')) {
    function show503(string $message = ''): void
    {
        throw new \orange\framework\exceptions\http\Http503($message);
    }
}

// 301 Moved Permanently
if (!function_exists('redirect301')) {
    function redirect301(string $url, string $message = ''): void
    {
        throw new \orange\framework\exceptions\http\Http301($url, $message);
    }
}

/*
 * Convert PHP error to an exception
 */
if (!function_exists('errorHandler')) {
    function errorHandler(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        if (error_reporting() & $errno) {
            throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        }

        return false;
    }
}

/*
 * default exception handler
 *
 * override as needed
 */
if (!function_exists('exceptionHandler')) {
    function exceptionHandler(Throwable $exception): void
    {
        // Http exceptions (Http404, Http403, etc.) are deliberate control flow - e.g.
        // thrown by show404() - not unexpected failures, so they always get the full
        // Error-page treatment below regardless of error_reporting. A non-Http
        // exception is a genuinely unexpected failure; if error_reporting has been
        // fully silenced (0) - a common production hardening setting - skip building
        // a detailed error page and just fail generically instead.
        if (!($exception instanceof \orange\framework\exceptions\http\Http) && error_reporting() == 0) {
            http_response_code(500);
            exit(1);
        }

        // make a direct instance of Error Class
        // override this function if you want to use your own class
        \orange\framework\Error::getInstance([], container(), $exception);

        // Error::getInstance()'s constructor always ends by calling sendOutput(),
        // which always exit()s - so under normal circumstances this line never
        // actually runs. Kept as a fail-safe in case that ever changes, so an
        // uncaught exception can never fall through this handler silently.
        exit(1);
    }
}
