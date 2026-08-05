<?php

declare(strict_types=1);

namespace orange\framework\interfaces;

/**
 * The response, buffered until something asks for it to be sent.
 *
 * Body, headers, content type, charset and status code accumulate here and
 * nothing reaches the client until send(). That is the point of the indirection:
 * a listener on before.output can still rewrite any of it, and echoing from a
 * controller would have escaped that. Buffering is also what makes the body
 * re-readable - get() and __toString() hand back what has been written so far
 * without consuming it.
 *
 * header() appends by default. The two replace modes differ only in how much of
 * the header they treat as the identity to match on, which is the difference
 * between replacing a header and replacing one entry of a repeated header:
 *
 *   REPLACEALL    matches up to ':' or a space - the header name.
 *                 'Set-Cookie: a=1' displaces every existing Set-Cookie.
 *   REPLACEEXACT  matches up to ';', '=' or ',' - name plus its first token.
 *                 'Set-Cookie: a=1' displaces only the existing cookie 'a'.
 *
 * Matching is case insensitive in both, and NO skips the search entirely.
 *
 * JSONOPTIONS hex-escapes <, >, ', " and & so encoded output is inert if it
 * lands in an HTML context, while leaving unicode unescaped; PRETTYJSONOPTIONS
 * is the same set plus pretty printing. Prefer them to a bare json_encode().
 */
interface OutputInterface
{
    public const NO = 0;
    public const REPLACEALL = 1;
    public const REPLACEEXACT = 2;

    public const HTML = 'text/html';
    public const JSON = 'application/json';
    public const JSONOPTIONS = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE;
    public const PRETTYJSONOPTIONS = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT;

    public function __toString(): string;

    public function write(string $string, bool $append = true): self;
    public function get(): string;
    public function flush(): self;

    public function header(string $value, int $replace = self::NO, bool $prepend = false): self;
    /**
     * @return array<string, string>
     */
    public function getHeaders(): array;
    public function flushHeaders(): self;

    public function contentType(string $contentType, string $fallback = ''): self;
    public function getContentType(): string;

    public function charSet(string $charSet): self;
    public function getCharSet(): string;

    public function responseCode(int $code): self;
    public function getResponseCode(): int;

    public function flushAll(): self;
    public function redirect(string $url, int $responseCode = -1, bool $exit = true, bool $allowExternal = false): void;
    public function forceHttps(): void;

    public function send(bool|int $exit = false): void;
}
