<?php

declare(strict_types=1);

namespace orange\framework\installer;

/**
 * One thing the installer intends to do to one file.
 *
 * A plan is built entirely before anything is written, which is what makes
 * --dry-run honest: the same objects are produced either way, and applying is
 * just walking them. It also means a conflict is reported alongside everything
 * else rather than discovered halfway through a half-finished install.
 *
 * $contents holds the bytes to write when they are not simply the source file's
 * - a migration whose class had to be renamed, or a config file with merged
 * content spliced in. Null means a straight copy.
 */
final class InstallAction
{
    /** Write the file; it is not there yet, or -o was given. */
    public const string COPY = 'copy';

    /** Splice content into an existing config file after its marker. */
    public const string MERGE = 'merge';

    /** Nothing to do - already installed, or identical on disk. */
    public const string SKIP = 'skip';

    /** Refused: the destination exists and differs, or cannot be written safely. */
    public const string CONFLICT = 'conflict';

    public function __construct(
        public readonly string $type,
        public readonly string $source,
        public readonly string $destination,
        public readonly string $reason = '',
        public readonly ?string $contents = null,
    ) {
    }

    /** True for the two types that actually touch the filesystem. */
    public function writes(): bool
    {
        return $this->type === self::COPY || $this->type === self::MERGE;
    }
}
