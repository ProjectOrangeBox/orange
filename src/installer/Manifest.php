<?php

declare(strict_types=1);

namespace orange\framework\installer;

use orange\framework\exceptions\InstallerException;

/**
 * The optional install.php a package may put at the root of its install/ tree.
 *
 * The mirrored directory says where files go - position is the instruction, and
 * that covers everything the installer can do on its own. This covers what a
 * directory cannot say: what the package needs present before its files are
 * worth copying, and what the person has to do afterwards. A migration that has
 * been copied but not run is not installed in any sense the user cares about,
 * and only the package knows that `composer db:migrate` is the next step.
 *
 * Every key is optional. A package with nothing to declare simply omits the
 * file and gets Manifest::none().
 *
 *   return [
 *       'name' => 'orange/acl',
 *       'requires' => ['pdo_mysql'],
 *       'php' => '8.4',
 *       'after' => ['Run: composer db:migrate', 'Run: composer db:seed'],
 *   ];
 */
final class Manifest
{
    /**
     * @param string[] $requires PHP extensions that must be loaded.
     * @param string[] $after    Lines printed once the install succeeds.
     */
    public function __construct(
        public readonly string $name = '',
        public readonly array $requires = [],
        public readonly string $php = '',
        public readonly array $after = [],
    ) {
    }

    /** The manifest a package that shipped no install.php gets. */
    public static function none(): self
    {
        return new self();
    }

    /**
     * Read install.php from a package's install directory.
     *
     * A file that is present but does not return an array is an error worth
     * stopping for - it is a typo in the package, not an absent manifest.
     *
     * @throws InstallerException
     */
    public static function fromDirectory(string $directory): self
    {
        $path = $directory . '/install.php';

        if (!is_file($path)) {
            return self::none();
        }

        $raw = require $path;

        if (!is_array($raw)) {
            throw new InstallerException($path . ' must return an array.');
        }

        return new self(
            name: self::asString($raw['name'] ?? ''),
            requires: self::asStrings($raw['requires'] ?? []),
            php: self::asString($raw['php'] ?? ''),
            after: self::asStrings($raw['after'] ?? []),
        );
    }

    /**
     * Reasons this package cannot be installed here, or [] when it can.
     *
     * Checked before the plan is built rather than after: copying half a
     * package onto a machine missing the extension it needs leaves more mess
     * than refusing does.
     *
     * @return string[]
     */
    public function unmet(): array
    {
        $unmet = [];

        foreach ($this->requires as $extension) {
            if (!extension_loaded($extension)) {
                $unmet[] = 'PHP extension "' . $extension . '" is not loaded.';
            }
        }

        if ($this->php !== '' && !version_compare(PHP_VERSION, $this->php, '>=')) {
            $unmet[] = 'PHP ' . $this->php . ' or newer required, running ' . PHP_VERSION . '.';
        }

        return $unmet;
    }

    protected static function asString(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @return string[]
     */
    protected static function asStrings(mixed $value): array
    {
        if (is_scalar($value)) {
            return [(string) $value];
        }

        if (!is_array($value)) {
            return [];
        }

        $strings = [];

        foreach ($value as $entry) {
            if (is_scalar($entry)) {
                $strings[] = (string) $entry;
            }
        }

        return $strings;
    }
}
