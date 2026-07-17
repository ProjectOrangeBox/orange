<?php

declare(strict_types=1);

namespace orange\framework;

use orange\framework\base\Singleton;
use orange\framework\interfaces\InputInterface;
use orange\framework\traits\ConfigurationTrait;

/**
 * Class Input
 *
 * Centralizes access to request state by wrapping superglobals and injected config inside a singleton Input service; the constructor merges supplied config, stores query/body/cookie/file data, normalizes server values, and records the raw input stream.
 * Utility accessors (request, query, cookie, file) pull values via a shared extract helper while logging calls, giving callers filtered slices or the whole dataset without touching superglobals directly.
 * Server and header lookups normalize keys (lowercase, underscores to spaces, HTTP/Server prefixes stripped) so the rest of the class can query consistent identifiers.
 * URL helpers surface the request URI and individual segments, backed by the normalized server data (packages/orange/src/Input.php:231, packages/orange/src/Input.php:261).
 * Methods such as contentType, requestMethod, and requestType interpret headers and overrides to expose the effective content type, HTTP verb (including _method overrides), and whether the call is HTML/AJAX/CLI.
 * Boolean helpers report on AJAX, CLI, and HTTPS status, including the ability to return scheme strings when requested.
 * detectInputStream parses the raw body for URL-encoded or JSON payloads on non-POST verbs so the $request array stays populated even when PHP would normally leave it empty.
 *
 * 1. Core Purpose:
 * - Unified API for accessing request data across different sources and methods.
 * - Supports method overrides and JSON/form-encoded body parsing.
 * - Identifies request type (AJAX, CLI, HTTPS) and normalizes server data.
 *
 * 2. Key Properties:
 * - @property array $query        Parsed query string ($_GET)
 * - @property array $request      Parsed request body ($_POST, JSON, etc.)
 * - @property array $server       Normalized $_SERVER values
 * - @property array $cookies      Parsed cookies ($_COOKIE)
 * - @property array $files        Uploaded files ($_FILES)
 * - @property array $headers      Extracted HTTP headers
 * - @property-read string $inputStream Raw body input stream
 *
 * 3. Important Methods:
 * - request(?string $key = null, mixed $default = null): mixed
 *     Get data from POST/PUT/JSON body.
 *
 * - query(?string $key = null, mixed $default = null): mixed
 *     Get data from query string.
 *
 * - cookie(?string $key = null, mixed $default = null): mixed
 *     Get cookie value.
 *
 * - file(null|int|string $key = null, mixed $default = []): mixed
 *     Get uploaded file metadata.
 *
 * - server(?string $key = null, mixed $default = null): mixed
 *     Get normalized server variable.
 *
 * - header(?string $key = null, mixed $default = null): mixed
 *     Get HTTP header value.
 *
 * - getUrl(?int $component = null): mixed
 *     Get full or component of the request URL.
 *
 * - requestUri(): string
 *     Get URI path.
 *
 * - uriSegment(int $segmentNumber): string
 *     Get URI segment by index (1-based).
 *
 * - contentType(bool $asLowercase = true): string
 *     Get request content type.
 *
 * - requestMethod(bool $asLowercase = true): string
 *     Get HTTP method, supports `_method` override.
 *
 * - requestType(bool $asLowercase = true): string
 *     Detect request type: html, ajax, cli.
 *
 * - isAjaxRequest(): bool
 *     Check if request was made via AJAX.
 *
 * - isCliRequest(): bool
 *     Check if request was made via CLI.
 *
 * - isHttpsRequest(bool $asString = false): bool|string
 *     Check if request is over HTTPS or return "https"/"http".
 *
 * 4. Configuration & Setup:
 * - Constructor expects a config array with optional keys:
 *   - query, request, server, cookies, files, inputStream, php_sapi, stdin
 * - Merges config using ConfigurationTrait's `mergeConfigWith()` method.
 *
 * 5. Error Handling:
 * - Uses safe access (`??`) to avoid undefined index errors.
 * - Gracefully falls back to defaults when data is missing.
 * - Applies fallback method resolution if HTTP method override is missing.
 *
 * 6. Big Picture:
 * - Simplifies interaction with superglobals and raw input.
 * - Enhances testability by injecting custom data via config.
 * - Helps enforce a consistent and test-friendly way of handling input data.
 * - Acts as a bridge between raw HTTP layer and application logic.
 */
class Input extends Singleton implements InputInterface
{
    /** include ConfigurationTrait methods */
    use ConfigurationTrait;

    // individual storage for each of the input variables
    protected array $query = [];
    protected array $request = [];
    protected array $cookies = [];
    protected array $files = [];
    protected array $server = [];
    protected array $headers = [];

    // input stream
    protected string $inputStream = '';

    /**
     * Protected constructor to enforce the singleton pattern.
     *
     * @param array $config Configuration data.
     */
    protected function __construct(array $config)
    {
        logMsg('INFO', __METHOD__);

        // merge the supplied config with defaults
        $this->config = $this->mergeConfigWith($config, false);

        // store the various input arrays
        $this->query = $this->config['query'] ?? [];
        $this->cookies = $this->config['cookies'] ?? [];
        $this->files = $this->config['files'] ?? [];

        // build normalized server and headers arrays
        $this->detectServerHeaders($this->server, $this->headers, $this->config['server'] ?? []);

        // save this to provide it as a read only property
        $this->inputStream = $this->config['input'] ?? '';

        // detect request body data types and populate $this->request
        $this->request = $this->detectRequest($this->contentType(), $this->inputStream, $this->config['request']);
    }

    /**
     * Retrieve data from the request (POST/PUT/PATCH) body.
     *
     * @param string|null $key Specific key to fetch; when null, returns the full dataset.
     * @param mixed $default Fallback value when the key is absent.
     *
     * @return mixed
     */
    public function request(?string $key = null, mixed $default = null): mixed
    {
        logMsg('INFO', __METHOD__ . ' ' . $key);

        return $this->extract($this->request, $key, $default);
    }

    /**
     * Retrieve data from the query string parameters.
     *
     * @param string|null $key Specific key to fetch; when null, returns the full dataset.
     * @param mixed $default Fallback value when the key is absent.
     *
     * @return mixed
     */
    public function query(?string $key = null, mixed $default = null): mixed
    {
        logMsg('INFO', __METHOD__ . ' ' . $key);

        return $this->extract($this->query, $key, $default);
    }

    /**
     * The raw apache input stream
     *
     * @return string
     */
    public function inputStream(): string
    {
        return $this->inputStream;
    }

    /**
     * Retrieve data from the incoming cookies.
     *
     * @param string|null $key Specific key to fetch; when null, returns the full dataset.
     * @param mixed $default Fallback value when the key is absent.
     *
     * @return mixed
     */
    public function cookie(?string $key = null, mixed $default = null): mixed
    {
        logMsg('INFO', __METHOD__ . ' ' . $key);

        return $this->extract($this->cookies, $key, $default);
    }

    /**
     * Retrieve uploaded file metadata.
     *
     * @param int|string|null $key Specific entry to fetch; accepts nested indexes.
     * @param mixed $default Fallback value when the key is absent.
     *
     * @return mixed
     */
    public function file(null|int|string $key = null, mixed $default = []): mixed
    {
        logMsg('INFO', __METHOD__ . ' ' . $key);

        return $this->extract($this->files, $key, $default);
    }

    /**
     * Retrieve values from server parameters with normalized keys.
     *
     * @param string|null $key Specific key to fetch; when null, returns the full dataset.
     * @param mixed $default Fallback value when the key is absent.
     *
     * @return mixed
     */
    public function server(?string $key = null, mixed $default = null): mixed
    {
        logMsg('INFO', __METHOD__ . ' ' . $key);

        $key = $key === null ? null : $this->normalizeServerKey($key);

        return $this->extract($this->server, $key, $default);
    }

    /**
     * Retrieve HTTP headers derived from server parameters.
     *
     * @param string|null $key Specific key to fetch; when null, returns the full dataset.
     * @param mixed $default Fallback value when the key is absent.
     *
     * @return mixed
     */
    public function header(?string $key = null, mixed $default = null): mixed
    {
        logMsg('INFO', __METHOD__ . ' ' . $key);

        $key = $key === null ? null : $this->normalizeServerKey($key);

        return $this->extract($this->headers, $key, $default);
    }

    /**
     * Resolve the requested URL or a specific component of it.
     *
     * @param int|null $component Optional parse_url component to return.
     *
     * @return mixed
     */
    public function getUrl(?int $component = null): mixed
    {
        logMsg('INFO', __METHOD__ . ' ' . $component);

        $parseUrl = $this->server('request_uri', '');

        return $component == null ? $parseUrl : parse_url($parseUrl, $component);
    }

    /**
     * Retrieve the current request URI path component.
     *
     * @return string
     */
    public function requestUri(): string
    {
        $uri = parse_url($this->server('request_uri', ''), self::PATH);

        logMsg('INFO', __METHOD__ . ' ' . $uri);

        return $uri;
    }

    /**
     * Retrieve a single segment of the current request URI.
     *
     * @param int $segmentNumber One-based segment index.
     *
     * @return string
     */
    public function uriSegment(int $segmentNumber): string
    {
        logMsg('INFO', __METHOD__ . ' ' . $segmentNumber);

        $segs = explode('/', ltrim($this->requestUri(), '/'));

        return $segs[$segmentNumber - 1] ?? '';
    }

    /**
     * Retrieve the request content type with configurable casing.
     *
     * @param bool $asLowercase True to return lowercase; false returns uppercase.
     *
     * @return string
     */
    public function contentType(bool $asLowercase = true): string
    {
        $type = $this->server('content type', '');

        logMsg('DEBUG', __METHOD__ . $type);

        return $asLowercase ? strtolower($type) : strtoupper($type);
    }

    /**
     * Determine the effective HTTP method, honoring override conventions.
     *
     * @param bool $asLowercase True to return lowercase; false returns uppercase.
     *
     * @return string
     */
    public function requestMethod(bool $asLowercase = true): string
    {
        /**
         * You can override the http method by setting on of the following in your http request
         */
        $null = chr(0);

        if ($this->server('http_x_http_method_override', $null) !== $null) {
            $method = $this->server('http_x_http_method_override', '');
        } elseif ($this->query('_method', $null) !== $null) {
            $method = $this->query('_method');
        } elseif ($this->request('_method', $null) !== $null) {
            $method = $this->request('_method');
        } elseif ($this->server('request_method', $null) !== $null) {
            $method = $this->server('request_method', '');
        } else {
            // I guess it's a CLI request?
            $method = 'cli';
        }

        logMsg('DEBUG', __METHOD__ . $method);

        return $asLowercase ? strtolower($method) : strtoupper($method);
    }

    /**
     * Detect the request type (HTML, AJAX, CLI) based on headers and environment.
     *
     * @param bool $asLowercase True to return lowercase; false returns uppercase.
     *
     * @return string
     */
    public function requestType(bool $asLowercase = true): string
    {
        // determine the request type
        if (($this->server('http_x_requested_with', '') == 'xmlhttprequest') || (strpos($this->server('http_accept', ''), 'application/json') !== false)) {
            $requestType = 'ajax';
        } elseif (strtolower($this->config['php_sapi'] ?? '') === 'cli' || ($this->config['stdin'] ?? false) === true) {
            $requestType = 'cli';
        } else {
            $requestType = 'html';
        }

        logMsg('DEBUG', __METHOD__ . $requestType);

        return $asLowercase ? strtolower($requestType) : strtoupper($requestType);
    }

    /**
     * Determine whether the request originated from an AJAX call.
     *
     * @return bool
     */
    public function isAjaxRequest(): bool
    {
        return $this->requestType() == 'ajax';
    }

    /**
     * Determine whether the request originated from the command line.
     *
     * @return bool
     */
    public function isCliRequest(): bool
    {
        return $this->requestType() == 'cli';
    }

    /**
     * Determine whether the request is served over HTTPS.
     *
     * @param bool $asString True to return "https" or "http" instead of a boolean.
     *
     * @return bool|string
     */
    public function isHttpsRequest(bool $asString = false): bool|string
    {
        logMsg('INFO', __METHOD__ . ' ' . $asString);

        // set the default to not https unless we find something else
        $isHttps = false;

        if ($this->server('https', '') == 'on' || $this->server('http_x_forwarded_proto', '') === 'https' || $this->server('http_front_end_https', '') !== '') {
            $isHttps = true;
        }

        logMsg('INFO', __METHOD__ . ' ' . ($isHttps ? 'true' : 'false'));

        $return = $isHttps;

        if ($asString) {
            $return = $isHttps ? 'https' : 'http';
        }

        return $return;
    }

    /**
     * Set the Application globals array
     *
     * @param array $globals Overrides merged over the captured superglobals.
     * @return array
     */
    public static function setGlobals(array $globals = []): array
    {
        // only instance of globals
        static $staticGlobals = [];

        // this only allow these to be set once!
        if (empty($staticGlobals)) {
            $staticGlobals = array_replace([
                'query' => $_GET,
                'request' => $_POST,
                'server' => $_SERVER,
                'cookie' => $_COOKIE,
                'files' => $_FILES,
                'php_sapi' => PHP_SAPI, // string
                'stdin' => defined('STDIN'), // boolean
                'input' => file_get_contents('php://input'), // string
            ], $globals);

            // unset for security
            unset($_GET);
            unset($_POST);
            unset($_SERVER);
            unset($_COOKIE);
            unset($_FILES);
            unset($_REQUEST);

            // NOTE - $_ENV managed by Application & $_SESSION managed independently
        }

        return $staticGlobals;
    }

    /**
     * Wrapper to get the globals from setGlobals
     *
     * @return array
     */
    public static function fromGlobals(): array
    {
        return static::setGlobals();
    }

    /**
     * Normalize and store server parameters, extracting HTTP headers.
     *
     * @param array $server Raw server parameters.
     *
     * @return void
     */
    protected function detectServerHeaders(array &$server, array &$headers, array $input): void
    {
        foreach ($input as $key => $value) {
            $normalizedKey = $this->normalizeServerKey($key);

            $server[$normalizedKey] = $value;

            // CONTENT_* are not prefixed with HTTP_
            if (strpos($key, 'HTTP_') === 0 || in_array($key, ['CONTENT_LENGTH', 'CONTENT_MD5', 'CONTENT_TYPE'])) {
                $headers[$normalizedKey] = $value;
            }
        }
    }

    /**
     * Normalize server keys by lowercasing, replacing underscores with spaces, and stripping HTTP/Server prefixes.
     *
     * @param string $key
     * @return string
     */
    protected function normalizeServerKey(string $key): string
    {
        return str_replace('_', ' ', str_replace(['http_', 'server_'], '', strtolower($key)));
    }

    /**
     * Parse input stream based on the content type sent
     * populates $this->request accordingly
     * called from constructor
     *
     * @param string $contentType
     * @param string $inputStream
     * @param array $postedRequest
     * @return array
     */
    protected function detectRequest(string $contentType, string $inputStream, array $postedRequest): array
    {
        if (empty($contentType)) {
            // just use what was sent in from request
            $request = $postedRequest;
        } elseif (strpos($contentType, 'multipart/form-data', 0) !== false) {
            // use $_POST
            $request = $postedRequest;
            // files in $_FILES
        } elseif (strpos($contentType, 'application/x-www-form-urlencoded', 0) !== false) {
            // use stream
            parse_str($inputStream, $request);
            // no files attached
        } elseif (strpos($contentType, 'text/plain', 0) !== false) {
            // raw text has no key/value structure; the body is still available via
            // inputStream(). fall back to whatever was posted so $request stays an array
            $request = $postedRequest;
            // no files attached
        } elseif (strpos($contentType, 'application/json', 0) !== false) {
            // use stream and convert to json; guard against malformed json (json_decode
            // returns null) so $request is always an array
            $request = json_decode($inputStream, true) ?? [];
            // no files attached
        } else {
            // fall back to what was sent in
            $request = $postedRequest;
        }

        return $request;
    }

    /**
     * helper to extract a value from an array or return the whole array
     * if the key is null; returns default if the key is not found
     * used by request(), query(), cookie(), file(), server(), header()
     *
     * @param array $array
     * @param mixed $key
     * @param mixed|null $default
     * @return mixed
     */
    protected function extract(array $array, mixed $key, mixed $default = null): mixed
    {
        return $key === null ? $array : ($array[$key] ?? $default);
    }
}
