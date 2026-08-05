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
     *
     * @var array<array-key, string>
     */
    protected array $headers = [];

    /**
     * The HTTP response status code
     */
    protected int $responseCode = 200;

    /**
     * Maps internal string keys to HTTP status codes
     *
     * @var array<string, int>
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
     *
     * @var array<string, string>
     */
    protected array $mimes = [];

    /**
     * Constructor is protected to enforce Singleton pattern.
     * Use Singleton::getInstance() to obtain an instance.
     *
     * @param array<string, mixed> $config Configuration array.
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
     * Same-origin targets (a path, or anything without a host) always pass. An
     * off-site target has to name a host in the "allowed hosts" allowlist, or the
     * call has to say `allowExternal: true`. That default is what keeps the common
     * mistake - handing this a `?return=` parameter straight off the request -
     * from being an open redirect used to lend a phishing page your domain.
     *
     * @param string $url Target URL for redirection.
     * @param int $responseCode HTTP status code for the redirection.
     * @param bool $exit Whether to terminate script execution after redirection.
     * @param bool $allowExternal Skip the off-site check for a target the caller
     *        knows is trustworthy (a payment provider, an OAuth endpoint). Keep it
     *        false for anything derived from the request.
     * @throws OutputException If the target is off-site and neither allowlisted nor
     *         explicitly permitted, uses a scheme other than http/https, or contains
     *         control characters.
     */
    public function redirect(string $url, int $responseCode = 0, bool $exit = true, bool $allowExternal = false): void
    {
        logMsg('DEBUG', __METHOD__ . ' ' . $url . ' ' . $responseCode . ' ' . $exit);

        $url = $this->resolveRedirectTarget($url, $allowExternal);

        $responseCode = ($responseCode == 0) ? $this->config['default redirect code'] : $responseCode;

        $this->flushAll()
            ->header('Location: ' . $url, self::REPLACEALL)
            ->responseCode($responseCode)
            ->send($exit);
    }

    /**
     * Decide whether a redirect target is safe to put in a Location header.
     *
     * @param string $url The requested target.
     * @param bool $allowExternal Whether the caller has vouched for an off-site target.
     * @return string The target, unchanged, when it is permitted.
     * @throws OutputException When it is not.
     */
    protected function resolveRedirectTarget(string $url, bool $allowExternal): string
    {
        // A Location header is one line. PHP's header() already refuses embedded
        // CR/LF, but failing here says why, and also catches the other control
        // characters browsers quietly strip before following the URL - which is
        // how "java\nscript:" style filter evasion works.
        if (preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
            throw new OutputException('Redirect target contains control characters.');
        }

        // the caller has taken responsibility for this one
        if ($allowExternal) {
            return $url;
        }

        // Browsers treat a backslash in the authority like a forward slash, so
        // "/\evil.com" and "https:/\evil.com" are off-site even though parse_url()
        // reads them as paths. Normalize before deciding - but redirect to the
        // original string, since it is only the decision that needs the rewrite.
        $parts = parse_url(str_replace('\\', '/', $url));

        if ($parts === false) {
            throw new OutputException('Redirect target is not a parsable url: "' . $url . '"');
        }

        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;

        // no host and no scheme - a path or a relative reference, so same origin
        // by construction. (A leading "//" parses as a host, so it lands below.)
        if ($host === null && $scheme === null) {
            return $url;
        }

        // "javascript:", "data:", "mailto:" and friends never belong in a redirect
        if ($scheme !== null && !in_array(strtolower($scheme), ['http', 'https'], true)) {
            throw new OutputException('Redirect target scheme "' . $scheme . '" is not allowed.');
        }

        // a scheme with no host ("http:foo") is malformed enough to refuse
        if ($host === null) {
            throw new OutputException('Redirect target has a scheme but no host: "' . $url . '"');
        }

        // An empty allowlist means no off-site target is trusted, so this fails
        // closed rather than waving the redirect through.
        if (!in_array($host, $this->config['allowed hosts'] ?? [], true)) {
            throw new OutputException('Refusing to redirect off-site to "' . $host . '". Add it to the "allowed hosts" config, or pass allowExternal: true if this target is deliberate.');
        }

        return $url;
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
            // preg_split() returns false if the pattern fails to compile;
            // indexing that is a fatal rather than a header comparison
            $split = preg_split($splitOn, $value) ?: [$value];
            $prefix = strtolower($split[0]);
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
     * @return array<array-key, string> An array of HTTP headers.
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
        // responseCode() only guarantees the code is within 100-599, not that it
        // is one the status map knows - 299 is accepted and has no entry. Reading
        // it blind raised an "Undefined array key" warning and produced a status
        // line with a trailing space and no reason phrase. Fall back to the
        // generic phrase for the code's class instead, which is what RFC 9110
        // says a recipient should assume for an unrecognized code anyway.
        $reason = $this->config['status codes'][$responseCode] ?? $this->genericReasonPhrase($responseCode);

        return $this->input->server('server_protocol', 'HTTP/1.0') . ' ' . $responseCode . ' ' . $reason;
    }

    /**
     * The reason phrase for a status code the status map has no entry for.
     *
     * @param int $responseCode
     * @return string
     */
    protected function genericReasonPhrase(int $responseCode): string
    {
        return match (intdiv($responseCode, 100)) {
            1 => 'Informational',
            2 => 'Success',
            3 => 'Redirection',
            4 => 'Client Error',
            5 => 'Server Error',
            default => 'Unknown',
        };
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
     * Reads the Origin header; when it names a cross-origin caller listed in the "allowed cors"
     * config, sets Access-Control-Allow-Origin (plus a Vary: Origin header and, if opted into via
     * config, Access-Control-Allow-Credentials). When the Origin is cross-origin but not allowed,
     * the response is sent and the script exits immediately without the
     * Access-Control-Allow-Origin header. For OPTIONS preflight requests, echoes back the requested
     * method/headers as Access-Control-Allow-Methods/Access-Control-Allow-Headers, then sends and
     * exits.
     *
     * A request that names this application's own origin is not cross-origin and is left alone -
     * see isSameOrigin().
     *
     * @return void
     */
    public function handleCors(): void
    {
        $httpOrigin = $this->input->server('HTTP_ORIGIN');

        // Allow from any origin.
        //
        // Same-origin requests are excluded rather than run through the allowlist:
        // a browser attaches Origin to same-origin requests too - to every non-GET
        // navigation, so an ordinary <form method="post"> carries one. Present is
        // therefore not the same as cross-origin, and treating it so answered this
        // site's own form posts with an empty 200, killed here before the router
        // ever ran.
        if ($httpOrigin !== null && !$this->isSameOrigin($httpOrigin)) {
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

    /**
     * Whether an Origin header names the origin this very request was made to.
     *
     * Derived from the request (scheme + Host) rather than from config: "same origin"
     * is a property of the request, not of the deployment, so this needs no setting to
     * keep in step with the port the app happens to be served on.
     *
     * The Host header is client-supplied, but nothing is trusted to it here - a forged
     * Host makes a genuinely cross-origin request look same-origin only to a caller who
     * already controls both halves of the comparison, and the only thing it can win is
     * the absence of Access-Control-Allow-Origin headers it was never going to be given.
     * That is why this compares rather than calling resolveTrustedHost(), which exists
     * to keep an attacker's Host out of a Location header.
     *
     * @param string $httpOrigin The request's Origin header.
     * @return bool
     */
    protected function isSameOrigin(string $httpOrigin): bool
    {
        $host = (string) $this->input->server('http_host', '');

        if ($host === '') {
            return false;
        }

        // scheme and host are case-insensitive; the browser sends both lowercased and
        // omits the port when it is the scheme's default, which is how Host arrives too
        return strcasecmp($httpOrigin, (string) $this->input->isHttpsRequest(true) . '://' . $host) === 0;
    }
}
