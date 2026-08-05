<?php

declare(strict_types=1);

namespace orange\framework\installer;

/**
 * What each package has already put into this application, and in what state.
 *
 * Without this the installer can only ask "does the destination exist", which
 * cannot tell an untouched file it wrote last week from one the user has since
 * edited - so it must either clobber both or refuse both. Recording a hash at
 * install time separates the two: unchanged means skip silently, changed means
 * stop and say so, and -o is then a decision about the user's own edits rather
 * than a blanket hammer.
 *
 * It also answers the question a copied file otherwise cannot: where did this
 * migration come from. A phinx migration in database/migrations looks exactly
 * like one written by hand.
 *
 * Stored as JSON under var/ because that is already the application's
 * writable-state directory, and because a human reading it is the point.
 */
final class Receipt
{
    public const string PATH = '/var/installed-modules.json';

    /** @var array<string, array{installed: string, files: array<string, string>}> */
    protected array $packages = [];

    public function __construct(protected string $root)
    {
        $this->load();
    }

    /**
     * True when this package installed exactly these bytes at this path and
     * nothing has touched them since.
     *
     * @param string $relative Destination path relative to the application root.
     */
    public function isUnchanged(string $package, string $relative, string $contents): bool
    {
        $recorded = $this->packages[$package]['files'][$relative] ?? null;

        return $recorded !== null && $recorded === $this->hash($contents);
    }

    /** True when this package has a record for this path, whatever its state. */
    public function knows(string $package, string $relative): bool
    {
        return isset($this->packages[$package]['files'][$relative]);
    }

    /**
     * Note a file as installed. Held in memory until write().
     */
    public function record(string $package, string $relative, string $contents): void
    {
        if (!isset($this->packages[$package])) {
            $this->packages[$package] = ['installed' => '', 'files' => []];
        }

        $this->packages[$package]['installed'] = date('c');
        $this->packages[$package]['files'][$relative] = $this->hash($contents);
    }

    /**
     * Flush to disk, sorted so the file diffs cleanly between installs.
     */
    public function write(): void
    {
        $path = $this->root . self::PATH;
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            return;
        }

        ksort($this->packages);

        foreach ($this->packages as $name => $package) {
            ksort($package['files']);
            $this->packages[$name] = $package;
        }

        $json = json_encode($this->packages, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json !== false) {
            file_put_contents($path, $json . PHP_EOL, LOCK_EX);
        }
    }

    protected function hash(string $contents): string
    {
        // normalized on line endings only - a checkout that rewrote CRLF is not
        // a user edit, and treating it as one would make every install on a
        // Windows working copy report conflicts it cannot explain
        return 'sha256:' . hash('sha256', str_replace("\r\n", "\n", $contents));
    }

    protected function load(): void
    {
        $path = $this->root . self::PATH;

        if (!is_file($path)) {
            return;
        }

        $raw = file_get_contents($path);

        if ($raw === false) {
            return;
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            // an unreadable receipt is treated as an absent one: the worst that
            // costs is a conflict report the user has to read, where trusting
            // half-parsed contents could silently overwrite their edits
            return;
        }

        foreach ($decoded as $name => $package) {
            if (!is_string($name) || !is_array($package)) {
                continue;
            }

            $files = [];

            /** @var mixed $recorded */
            $recorded = $package['files'] ?? [];

            if (is_array($recorded)) {
                foreach ($recorded as $relative => $hash) {
                    if (is_string($relative) && is_string($hash)) {
                        $files[$relative] = $hash;
                    }
                }
            }

            $installed = $package['installed'] ?? '';

            $this->packages[$name] = [
                'installed' => is_string($installed) ? $installed : '',
                'files' => $files,
            ];
        }
    }
}
