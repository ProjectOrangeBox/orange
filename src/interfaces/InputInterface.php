<?php

declare(strict_types=1);

namespace orange\framework\interfaces;

/**
 * Also exposes request data via array access - $input['server']['content_type'] -
 * as a read-only alternative to the query()/request()/server()/etc. methods below.
 * offsetSet()/offsetUnset() must throw: implementations of this interface are
 * immutable snapshots of the request, not mutable state.
 */
interface InputInterface extends \ArrayAccess
{
    // HTTP Methods
    public const GET       = 'GET';
    public const POST      = 'POST';
    public const PUT       = 'PUT';
    public const DELETE    = 'DELETE';
    public const HEAD      = 'HEAD';
    public const OPTIONS   = 'OPTIONS';
    public const TRACE     = 'TRACE';
    public const CONNECT   = 'CONNECT';

    public const SCHEME = PHP_URL_SCHEME;
    public const HOST = PHP_URL_HOST;
    public const PORT = PHP_URL_PORT;
    public const USER = PHP_URL_USER;
    public const PASS = PHP_URL_PASS;
    public const PATH = PHP_URL_PATH;
    public const QUERY = PHP_URL_QUERY;
    public const FRAGMENT = PHP_URL_FRAGMENT;

    public function getUrl(int $component = -1);
    public function requestUri(): string;
    public function uriSegment(int $segmentNumber): string;

    public function contentType(bool $asLowercase = true): string;
    public function requestMethod(bool $asLowercase = true): string;
    public function requestType(bool $asLowercase = true): string;

    public function isAjaxRequest(): bool;
    public function isCliRequest(): bool;
    public function isHttpsRequest(bool $asString = false): bool|string;

    public function request(?string $key = null, mixed $default = null): mixed;
    public function query(?string $key = null, mixed $default = null): mixed;
    public function server(?string $key = null, mixed $default = null): mixed;
    public function header(?string $key = null, mixed $default = null): mixed;
    public function cookie(?string $key = null, mixed $default = null): mixed;
    public function file(null|int|string $key = null, mixed $default = null): mixed;
}
