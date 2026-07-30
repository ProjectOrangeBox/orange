<?php

declare(strict_types=1);

namespace orange\framework\interfaces;

interface CacheInterface
{
    public function get(string $key): mixed;
    public function set(string $key, mixed $value, ?int $ttl = null): bool;
    public function delete(string $key): bool;
    public function flush(): bool;
    /**
     * @param array<array-key, string> $keys
     * @return array<string, mixed> key => value, misses omitted
     */
    public function getMulti(array $keys): array;
    /**
     * @param array<string, mixed> $data
     * @return array<string, bool> key => whether it was stored
     */
    public function setMulti(array $data, ?int $ttl = null): array;
    /**
     * @param array<array-key, string> $keys
     * @return array<string, bool> key => whether it was deleted
     */
    public function deleteMulti(array $keys): array;
    public function increment(string $key, int $offset = 1, ?int $ttl = null): int;
    public function decrement(string $key, int $offset = 1, ?int $ttl = null): int;
}
