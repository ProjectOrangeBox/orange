<?php

declare(strict_types=1);

namespace orange\framework;

use orange\framework\base\Singleton;
use orange\framework\interfaces\InputInterface;
use orange\framework\traits\ConfigurationTrait;
use orange\framework\interfaces\OutputInterface;
use orange\framework\exceptions\output\Output as OutputException;

/**
 * Class Output
 *
 * Overview of Output.php
 *
 * This file defines the Output class in the orange\framework namespace.
 * It implements the OutputInterface and extends the Singleton base,
 * meaning there is only ever one instance used during the application lifecycle.
 * Its role is to manage all HTTP output — headers, status codes, content type, charset, buffering, redirects, and sending responses.
 *
 * ⸻
 *
 * 1. Core Responsibilities
 *  •   Response management: keeps track of what content and headers should be sent.
 *  •   Headers and status codes: allows setting, replacing, and flushing HTTP headers, as well as configuring response codes.
 *  •   Content-Type and charset: ensures correct MIME type and character encoding are applied.
 *  •   Redirects: performs HTTP redirects with configurable status codes.
 *  •   HTTPS enforcement: can force secure connections by redirecting to https://.
 *  •   Final send: flushes headers and body to the client, with optional script termination.
 *
 * ⸻
 *
 * 2. Key Properties
 *  •   $output → string buffer holding response body.
 *  •   $headers → array of headers waiting to be sent.
 *  •   $responseCode → numeric HTTP status code (default 200).
 *  •   $responseCodesInternalStringKeys → mapping of string names (like "ok") to numeric codes.
 *  •   $contentType → MIME type of response (e.g., text/html).
 *  •   $charSet → response charset (e.g., UTF-8).
 *  •   $mimes → supported MIME type mappings.
 *  •   $input → reference to the request InputInterface (needed for HTTPS enforcement and CLI checks).
 *
 * ⸻
 *
 * 3. Important Methods
 *  •   Redirects & Security
 *  •   redirect($url, $responseCode, $exit) → clears output, sets Location header, and issues redirect.
 *  •   forceHttps() → ensures secure scheme, redirects if not HTTPS.
 *  •   Output Handling
 *  •   write($string, $append) → writes content into buffer.
 *  •   get() → retrieves current buffer content.
 *  •   flush() → clears buffer.
 *  •   flushAll() → clears both headers and buffer.
 *  •   send($exit) → sends headers and content to client, optionally exits script.
 *  •   Headers
 *  •   header($value, $replace, $prepend) → sets a header with flexible replacement rules.
 *  •   getHeaders() → retrieves all queued headers.
 *  •   flushHeaders() → clears all headers.
 *  •   Response Codes
 *  •   responseCode($code) → sets numeric or string-mapped HTTP status.
 *  •   getResponseCode() → retrieves current status code.
 *  •   Content-Type and Charset
 *  •   contentType($type, $fallback) → sets MIME type with fallback resolution.
 *  •   getContentType() → returns current type.
 *  •   charSet($charSet) → sets charset and updates headers.
 *  •   getCharSet() → returns current charset.
 *  •   Helpers for headers and output
 *  •   getContentTypeHeader() → builds Content-Type header string.
 *  •   getResponseHeader() → builds HTTP status header string.
 *  •   phpEcho(), phpExit(), phpHeader() → wrapper methods around PHP functions, useful for testing and overriding.
 *  •   Cross-Origin Resource Sharing
 *  •   handleCors() → when enabled via config, sets Access-Control-* headers for allowed origins
 *      (optionally with credentials support) and answers OPTIONS preflight requests, sending and
 *      exiting immediately for disallowed origins or completed preflights.
 *
 * ⸻
 *
 * 4. Error Handling
 *  •   Throws OutputException when an invalid or unregistered content type is provided to contentType().
 *  •   Throws OutputException when forceHttps() cannot resolve a trusted host (no "allowed hosts" configured).
 *  •   Invalid or out-of-range HTTP status codes are not rejected with an exception; responseCode() silently
 *      falls back to 500 instead.
 *
 * ⸻
 *
 * 5. Big Picture
 *
 * The Output class is the final stage in the Orange framework’s request lifecycle. After routing and controller execution, this class:
 *  1.  Assembles the response (headers, body, status code).
 *  2.  Ensures correct protocol, content type, and charset.
 *  3.  Flushes everything to the client in a controlled and testable way.
 *
 * It centralizes output logic so controllers don’t need to deal with raw header() or echo calls.
 *
 * @package orange\framework
 */
class Output extends Singleton implements OutputInterface
{
    /** include ConfigurationTrait methods */
    use ConfigurationTrait;

    /**
     * Stores the output content to be sent to the client
     */
    protected string $output = '';

    /**
     * Stores HTTP headers to be sent
     */
    protected array $headers = [];

    /**
     * The HTTP response status code
     */
    protected int $responseCode = 200;

    /**
     * Maps internal string keys to HTTP status codes
     */
    protected array $responseCodesInternalStringKeys = [];

    /**
     * The Content-Type of the HTTP response
     */
    protected string $contentType = '';

    /**
     * The character set of the HTTP response
     */
    protected string $charSet = '';

    /**
     * MIME type mappings for content types
     */
    protected array $mimes = [];

    /**
     * Constructor is protected to enforce Singleton pattern.
     * Use Singleton::getInstance() to obtain an instance.
     *
     * @param array $config Configuration array.
     * @param InputInterface $input Input interface instance.
     * @throws OutputException If "force https" is enabled but no trusted host can be resolved,
     *         or if the configured/detected content type is not a known MIME type.
     */
    protected function __construct(array $config, protected InputInterface $input)
    {
        logMsg('DEBUG', __METHOD__);

        // merge the provided config with the default config
        // ($input is promoted on the constructor signature; force https and accepts-type detection use it)
        $this->config = $this->mergeConfigWith($config);

        if ($this->config['enable cors'] === true) {
            $this->handleCors();
        }

        // if force https is enabled in the config then we need to check if the request is https and if not redirect to the https version of the url
        if ($this->config['force https']) {
            $this->forceHttps();
        }

        // create a mapping of string keys to response codes for easy lookup
        $this->responseCodesInternalStringKeys = array_change_key_case(array_flip($this->config['status codes']), CASE_LOWER);

        $this->mimes = $this->config['mimes'] ?? [];

        // set the default response code
        $this->responseCode($this->responseCode);
        // set the default content type and charset based on config and auto-detection
        $this->detectAcceptsType($this->config['contentType']);
        $this->charSet($this->config['charSet']);
    }

    public function __toString(): string
    {
        // when the object is treated as a string, return the output content
        return $this->output;
    }

    /**
     * Redirects the client to a specified URL.
     *
     * @param string $url Target URL for redirection.
     * @param int $responseCode HTTP status code for the redirection.
     * @param bool $exit Whether to terminate script execution after redirection.
     */
    public function redirect(string $url, int $responseCode = 0, bool $exit = true): void
    {
        logMsg('DEBUG', __METHOD__ . ' ' . $url . ' ' . $responseCode . ' ' . $exit);

        $responseCode = ($responseCode == 0) ? $this->config['default redirect code'] : $responseCode;

        $this->flushAll()
            ->header('Location: ' . $url, self::REPLACEALL)
            ->responseCode($responseCode)
            ->send($exit);
    }

    /**
     * Enforces HTTPS protocol if the request is not already secure.
     *
     * @throws OutputException If no trusted host can be resolved (see resolveTrustedHost()).
     */
    public function forceHttps(): void
    {
        logMsg('DEBUG', __METHOD__);

        if (!$this->input->isHttpsRequest()) {
            // The Host header is client-supplied; reflecting it straight into the redirect
            // target is a host-header-injection open redirect. Only ever redirect to a host
            // we explicitly recognize. Redirect to the same URI over https using the
            // configured redirect status code.
            $host = $this->resolveTrustedHost($this->input->server('http_host', ''));

            $this->redirect('https://' . $host . $this->input->server('request_uri', ''), $this->config['force http response code']);
        }
    }

    /**
     * Resolve a host that is safe to place in a Location header.
     *
     * The incoming Host header is attacker-controllable, so it is only honored when it
     * appears in the configured "allowed hosts" allowlist. Otherwise the first allowed
     * host is used as the canonical redirect target. An empty allowlist means no host can
     * be trusted, so forcing https would be an open redirect — that fails closed.
     *
     * @param string $requestedHost The Host header from the request.
     * @return string A host that is safe to redirect to.
     * @throws OutputException If no allowed hosts are configured.
     */
    protected function resolveTrustedHost(string $requestedHost): string
    {
        $allowedHosts = $this->config['allowed hosts'] ?? [];

        if (empty($allowedHosts)) {
            throw new OutputException('Cannot force https safely: configure "allowed hosts" so the redirect never reflects the client-supplied Host header (open redirect).');
        }

        // honor the request host only when it is explicitly allowed; otherwise fall back
        // to the canonical (first) allowed host
        return in_array($requestedHost, $allowedHosts, true) ? $requestedHost : $allowedHosts[0];
    }

    /**
     * Flushes all headers and content.
     *
     * @return self
     */
    public function flushAll(): self
    {
        logMsg('DEBUG', __METHOD__);

        return $this->flushHeaders()->flush();
    }

    /**
     * Sends the output content and headers to the client.
     *
     * @param bool|int $exit Whether to exit after sending the output.
     */
    public function send(bool|int $exit = false): void
    {
        logMsg('DEBUG', __METHOD__);

        if (!$this->input->isCliRequest()) {
            foreach ($this->headers as $header) {
                $this->phpHeader($header);
            }
        }

        $this->phpEcho($this->output);

        if ($exit) {
            $exitCode = ($exit === true) ? 0 : $exit;
            $this->phpExit($exitCode);
        }
    }

    /**
     * Clears the output content.
     *
     * @return self
     */
    public function flush(): self
    {
        logMsg('DEBUG', __METHOD__);

        $this->output = '';

        return $this;
    }

    /**
     * Writes content to the output buffer.
     *
     * @param string $string Content to write.
     * @param bool $append Whether to append or overwrite the buffer.
     * @return self
     */
    public function write(string $string, bool $append = true): self
    {
        logMsg('DEBUG', __METHOD__);

        $this->output = $append ? $this->output . $string : $string;

        return $this;
    }

    /**
     * Gets the current output buffer.
     *
     * @return string
     */
    public function get(): string
    {
        return $this->output;
    }

    /**
     * Sets the Content-Type header.
     *
     * @param string $type MIME type.
     * @param string $fallback Fallback MIME type.
     * @return self
     */
    public function contentType(string $type, string $fallback = ''): self
    {
        // only build the message/context if this level is enabled - logMsg() alone would build it regardless
        if (isLogEnabled('DEBUG')) {
            logMsg('DEBUG', __METHOD__ . ' ' . $type);
        }

        // if they send in the shorthand content type convert it to a proper content type
        if (isset($this->mimes[$type])) {
            $detectedContentType = $this->mimes[$type];
        } elseif (isset($this->mimes[$fallback])) {
            $detectedContentType = $this->mimes[$fallback];
        } elseif (in_array($type, $this->mimes)) {
            $detectedContentType = $type;
        } elseif (in_array($fallback, $this->mimes)) {
            $detectedContentType = $fallback;
        } else {
            throw new OutputException('Unknown contentType(s) ' . $type . '/' . $fallback);
        }

        // only build the message/context if this level is enabled - logMsg() alone would build it regardless
        if (isLogEnabled('DEBUG')) {
            logMsg('DEBUG', __METHOD__ . ' ' . $detectedContentType);
        }

        $this->contentType = $detectedContentType;
        $this->header($this->getContentTypeHeader($this->contentType, $this->charSet), self::REPLACEALL);

        return $this;
    }

    /**
     * Retrieves the current content type.
     *
     * @return string
     */
    public function getContentType(): string
    {
        logMsg('DEBUG', __METHOD__);

        return $this->contentType;
    }

    /**
     * Sets the character set.
     *
     * @param string $charSet Character set to use.
     * @return self
     */
    public function charSet(string $charSet): self
    {
        // only build the message/context if this level is enabled - logMsg() alone would build it regardless
        if (isLogEnabled('DEBUG')) {
            logMsg('DEBUG', __METHOD__ . ' ' . $charSet);
        }

        $this->charSet = $charSet;

        $this->header($this->getContentTypeHeader($this->contentType, $this->charSet), self::REPLACEALL);

        return $this;
    }

    /**
     * Gets the current character set.
     *
     * @return string
     */
    public function getCharSet(): string
    {
        logMsg('DEBUG', __METHOD__);

        return $this->charSet;
    }

    /**
     * Sets an HTTP header for the response.
     *
     * This method supports flexible header management, including replacing or prepending headers.
     *
     * @param string $value The header string to be sent (e.g., 'Content-Type: text/html').
     * @param int $replace Flag indicating whether to replace existing headers with the same prefix.
     *                     - Use `self::NO` to prevent replacement.
     *                     - Use `self::REPLACEALL` to replace headers matching everything up to the
     *                       first `:` or space (i.e. matches by header/status-line name).
     *                     - Use `self::REPLACEEXACT` to replace headers matching everything up to the
     *                       first `;`, `=`, or `,` (a narrower match than REPLACEALL, useful for headers
     *                       whose value itself contains a colon or space, e.g. Set-Cookie).
     * @param bool $prepend Whether to prepend the header to the list instead of appending.
     * @return self
     */
    public function header(string $value, int $replace = self::NO, bool $prepend = false): self
    {
        // only build the message/context if this level is enabled - logMsg() alone would build it regardless
        if (isLogEnabled('DEBUG')) {
            logMsg('DEBUG', __METHOD__ . ' ' . $value . ' ' . $replace . ' ' . $prepend);
        }

        if ($replace != self::NO) {
            $splitOn = ($replace == self::REPLACEALL) ? '/(:| )/' : '/(;|=|,)/';
            $prefix = strtolower(preg_split($splitOn, $value)[0]);
            $prefixLength = strlen($prefix);

            foreach ($this->headers as $index => $headerValue) {
                if (substr(strtolower((string) $headerValue), 0, $prefixLength) == $prefix) {
                    unset($this->headers[$index]);
                }
            }
        }

        if ($prepend) {
            array_unshift($this->headers, $value);
        } else {
            $this->headers[] = $value;
        }

        return $this;
    }

    /**
     * Retrieves all currently set HTTP headers.
     *
     * This method returns all headers prepared for the response.
     *
     * @return array An array of HTTP headers.
     */
    public function getHeaders(): array
    {
        logMsg('DEBUG', __METHOD__);

        return array_values($this->headers);
    }

    /**
     * Clears all currently set HTTP headers.
     *
     * This method resets the headers array, ensuring no previously set headers are sent.
     *
     * @return self
     */
    public function flushHeaders(): self
    {
        logMsg('DEBUG', __METHOD__);

        $this->headers = [];

        return $this;
    }

    /**
     * Sets the HTTP response code.
     *
     * Allows setting a response code either by integer value or by a string key mapped internally.
     * An unrecognized string key resolves to 0, and any code outside the 100-599 range (including
     * that 0) is silently replaced with 500 - no exception is thrown for an invalid/unknown code.
     *
     * @param int|string $code The HTTP status code (e.g., 200, 404) or its string representation.
     * @return self
     */
    public function responseCode(int|string $code): self
    {
        // but if it is a string we need to try and detect the error number
        if (is_string($code)) {
            $code = $this->responseCodesInternalStringKeys[strtolower($code)] ?? 0;
        }

        // now bring it into http scope if necessary
        if ($code > 599 || $code < 100) {
            $code = 500;
        }

        // Save it
        $this->responseCode = (int)$code;

        // set final header response
        $this->header($this->getResponseHeader($this->responseCode), self::REPLACEALL, true);


        return $this;
    }

    /**
     * Retrieves the currently set HTTP response code.
     *
     * @return int The HTTP response code.
     */
    public function getResponseCode(): int
    {
        logMsg('DEBUG', __METHOD__);

        return $this->responseCode;
    }

    /**
     * Detects the appropriate response type based on the client's Accept header and sets the Content-Type accordingly.
     *
     * @param string $responseType
     * @return void
     */
    protected function detectAcceptsType(string $responseType)
    {
        if ($this->config['auto detect accepts type']) {
            if (!empty($accepts = $this->input->header('accept'))) {
                if (str_contains($accepts, 'application/json') || str_contains($accepts, 'text/javascript')) {
                    $responseType = 'application/json';
                } elseif (str_contains($accepts, 'text/html') || str_contains($accepts, 'application/xhtml+xml')) {
                    $responseType = 'text/html';
                }
            }
        }

        $this->contentType($responseType);
    }

    /**
     * Generates a Content-Type header string.
     *
     * Combines the content type and charset into a valid HTTP header string.
     *
     * @param string $contentType The MIME type for the content (e.g., 'text/html').
     * @param string $charSet The character set (e.g., 'UTF-8').
     * @return string The complete Content-Type header string.
     */
    protected function getContentTypeHeader(string $contentType, string $charSet): string
    {
        return 'Content-Type: ' . $contentType . '; charset=' . strtoupper($charSet);
    }

    /**
     * Generates an HTTP response status header string.
     *
     * Combines the HTTP protocol version, response code, and status message.
     *
     * @param int $responseCode The HTTP response status code (e.g., 200, 404).
     * @return string The full HTTP response header.
     */
    protected function getResponseHeader(int $responseCode): string
    {
        return $this->input->server('server_protocol', 'HTTP/1.0') . ' ' . $responseCode . ' ' . $this->config['status codes'][$responseCode];
    }

    /**
     * Outputs a string to the client.
     *
     * This method directly echoes the provided string, making it suitable for unit testing overrides.
     *
     * @param string $string The string to output.
     */
    protected function phpEcho(string $string): void
    {
        echo $string;
    }

    /**
     * Terminates script execution with an optional status code.
     *
     * Useful for controlling script termination during testing.
     *
     * @param int $status The exit status code (default is 0).
     */
    protected function phpExit(int $status = 0): void
    {
        exit($status);
    }

    /**
     * Sends an HTTP header.
     *
     * This method serves as a wrapper for PHP's native `header()` function,
     * allowing easier testing and overriding in unit tests.
     *
     * @param string $header The header string to send.
     * @param bool $replace Whether to replace a previous header with the same name.
     */
    protected function phpHeader(string $header, bool $replace = false): void
    {
        header($header, $replace);
    }

    /**
     * Handles Cross-Origin Resource Sharing (CORS) for the current request.
     *
     * Reads the Origin header; when it is present and listed in the "allowed cors" config, sets
     * Access-Control-Allow-Origin (plus a Vary: Origin header and, if opted into via config,
     * Access-Control-Allow-Credentials). When the Origin is present but not allowed, the response
     * is sent and the script exits immediately without the Access-Control-Allow-Origin header.
     * For OPTIONS preflight requests, echoes back the requested method/headers as
     * Access-Control-Allow-Methods/Access-Control-Allow-Headers, then sends and exits.
     *
     * @return void
     */
    public function handleCors(): void
    {
        $httpOrigin = $this->input->server('HTTP_ORIGIN');

        // Allow from any origin
        if ($httpOrigin !== null) {
            logMsg('DEBUG', 'CORS Http Origin: ' . $httpOrigin);

            // The Spec-Compliant Standard
            $this->responseCode(200);

            // Decide if the origin in 'HTTP_ORIGIN' is one
            if (in_array($httpOrigin, $this->config['allowed cors'], true)) {
                // the response headers depend on the request Origin, so mark it as
                // varying by Origin. Without this a shared cache (CDN/reverse proxy)
                // can store the Access-Control-Allow-Origin for one origin and replay
                // it to another.
                $this->header('Vary: Origin');
                // if it is allowed then send the Access-Control-Allow-Origin header
                $this->header('Access-Control-Allow-Origin: ' . $httpOrigin);
                // Only advertise credential support when the app explicitly opts in.
                // Combined with a reflected Origin this grants cookie/HTTP-auth access
                // to every allowed origin, so it defaults to off (token/bearer APIs do
                // not need it - the Authorization header is allowed via Allow-Headers).
                if (!empty($this->config['access-control-allow-credentials'])) {
                    $this->header('Access-Control-Allow-Credentials: true');
                }
                // cache for 1 day
                $this->header('Access-Control-Max-Age: ' . $this->config['access-control-max-age']);
            } else {
                // but omit the Access-Control-Allow-Origin header
                // send and exit
                $this->send(true);
            }
        }

        // Access-Control headers are received during OPTIONS requests
        if ($this->input->server('REQUEST_METHOD') == 'OPTIONS') {
            if ($this->input->server('HTTP_ACCESS_CONTROL_REQUEST_METHOD') !== null) {
                // queue via $this->header() (not the global header()) so it flows
                // through the same buffer/test seam as every other response header
                // and is flushed by the send() below.
                $this->header('Access-Control-Allow-Methods: ' . $this->config['access-control-allow-methods']);
            }

            if ($this->input->server('HTTP_ACCESS_CONTROL_REQUEST_HEADERS') !== null) {
                // Access-Control headers are received during OPTIONS requests
                $this->header('Access-Control-Allow-Headers: ' . $this->input->server('HTTP_ACCESS_CONTROL_REQUEST_HEADERS'));
            }

            // send and exit;
            $this->send(true);
        }
    }
}
