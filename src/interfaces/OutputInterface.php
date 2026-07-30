<?php

declare(strict_types=1);

namespace orange\framework\interfaces;

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
