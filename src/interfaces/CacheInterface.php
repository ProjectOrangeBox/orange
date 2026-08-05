<?php

declare(strict_types=1);

namespace orange\framework\interfaces;

/**
 * A key/value cache, over whichever backend is configured.
 *
 * Implementations live outside this package (orange\cache) and wrap files,
 * APCu, memcached, redis and so on. The contract is small on purpose: anything
 * a cache is asked to do here has to be expressible by all of them, which is
 * why there is no tagging, no atomic compare-and-set and no iteration.
 *
 * A cache is a cache - treat every get() as though it may miss. A null from
 * get() is a miss and a stored null are indistinguishable, so a value that
 * needs to be legitimately null wants a sentinel.
 *
 * ttl is in seconds, and null means the backend's configured default rather
 * than "forever". The multi variants exist to save round trips on network-backed
 * stores; they are not atomic as a group, and each answers with one entry per
 * requested key so the result can be indexed without checking. deleteMulti() is
 * the one place backends genuinely disagree - see its note.
 */
interface CacheInterface
{
    public function get(string $key): mixed;
    public function set(string $key, mixed $value, ?int $ttl = null): bool;
    public function delete(string $key): bool;
    public function flush(): bool;
    /**
     * @param array<array-key, string> $keys
     * @return array<string, mixed> key => value, with a miss present as null -
     *     every backend answers with one entry per requested key
     */
    public function getMulti(array $keys): array;
    /**
     * @param array<string, mixed> $data
     * @return array<string, bool> key => whether it was stored
     */
    public function setMulti(array $data, ?int $ttl = null): array;
    /**
     * @param array<array-key, string> $keys
     * @return array<string, bool> key => whether it was deleted. Backends
     *     disagree on a key that was not there: FilesCache reports true, the
     *     others false - so this is not a reliable existence check
     */
    public function deleteMulti(array $keys): array;
    public function increment(string $key, int $offset = 1, ?int $ttl = null): int;
    public function decrement(string $key, int $offset = 1, ?int $ttl = null): int;
}
