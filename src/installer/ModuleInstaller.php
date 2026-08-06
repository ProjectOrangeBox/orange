<?php

declare(strict_types=1);

namespace orange\framework\installer;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use orange\framework\exceptions\InstallerException;

/**
 * Copies the assets a composer package ships into the application that installs it.
 *
 * A package puts an install/ directory at its root whose contents mirror the
 * application root, so position is the instruction and there is nothing to keep
 * in sync with the files themselves:
 *
 *   <package>/install/
 *     install.php                     optional - see Manifest
 *     config/@acl.php                 merged into <root>/config/acl.php
 *     config/aclRoles.php             copied to <root>/config/aclRoles.php
 *     database/migrations/*.php       copied, package-prefixed - see below
 *     database/seeds/*.php            copied verbatim
 *     htdocs/css/acl.css              copied to <root>/htdocs/css/acl.css
 *     bin/, support/, var/            copied as-is
 *
 * Only those destinations are honored. A package cannot write to application/,
 * to the repo root, or anywhere outside the list - the point of the mirror is
 * that a reader can predict where a file lands, which stops being true the
 * moment arbitrary paths are allowed.
 *
 * Nothing is written until a complete plan exists. That is what makes --dry-run
 * worth trusting - it runs precisely the code a real install runs and stops
 * before apply() - and it means a conflict is reported next to everything else
 * instead of surfacing halfway through, leaving a package half-copied.
 *
 * Migrations are the one thing not copied byte-for-byte. Phinx keys a migration
 * by the numeric prefix of its filename and requires the class inside to match
 * the rest of that name, so two packages that both picked 20260801000001 would
 * collide on a number neither author can see the other choosing. The package
 * name is folded into both:
 *
 *   20260801000001_create_acl_tables.php   class CreateAclTables
 *          ->  20260801000001_orange_acl_create_acl_tables.php
 *                                          class OrangeAclCreateAclTables
 *
 * Seeds are left alone. They carry no version and no ordering - phinx runs them
 * by class name, which is what a person types - so a prefix would only make
 * `composer db:seed -s ...` harder to type without buying the collision safety
 * the version number needs. A seeder whose name is already taken is reported as
 * a conflict instead.
 */
final class ModuleInstaller
{
    /**
     * Destinations a package is allowed to write into.
     *
     * database/ is listed by its two subdirectories rather than as a whole, so
     * shipping a migration cannot also mean shipping something unexpected next
     * to phinx.php.
     *
     * @var string[]
     */
    public const array GROUPS = [
        'bin',
        'config',
        'database/migrations',
        'database/seeds',
        'htdocs',
        'support',
        'var',
    ];

    /** Filename prefix marking a config file as merged rather than copied. */
    public const string MERGE_PREFIX = '@';

    /** Merged content is spliced in after this line. */
    public const string MERGE_MARKER = '/* merged content below */';

    protected Receipt $receipt;
    protected Manifest $manifest;

    /**
     * @param string $root      Absolute application root, no trailing slash.
     * @param string $source    Absolute path to the package's install/ directory.
     * @param string $package   Composer name, e.g. 'orange/acl' - prefixes migrations, keys the receipt.
     * @param bool   $overwrite Replace destinations that exist and differ.
     *
     * @throws InstallerException
     */
    public function __construct(
        protected string $root,
        protected string $source,
        protected string $package,
        protected bool $overwrite = false,
    ) {
        $this->root = rtrim($root, '/');
        $this->source = rtrim($source, '/');

        if (!is_dir($this->source)) {
            throw new InstallerException('No install directory at "' . $this->source . '".');
        }

        $this->manifest = Manifest::fromDirectory($this->source);
        $this->receipt = new Receipt($this->root);
    }

    public function manifest(): Manifest
    {
        return $this->manifest;
    }

    /**
     * Everything this install would do, in destination order, writing nothing.
     *
     * @return InstallAction[]
     */
    public function plan(): array
    {
        $actions = [];

        foreach (self::GROUPS as $group) {
            $directory = $this->source . '/' . $group;

            if (!is_dir($directory)) {
                continue;
            }

            foreach ($this->filesIn($directory) as $file) {
                $actions[] = match (true) {
                    $group === 'database/migrations' => $this->planMigration($file),
                    $this->isMergeFile($file) => $this->planMerge($file),
                    default => $this->planCopy($file),
                };
            }
        }

        usort($actions, static fn(InstallAction $a, InstallAction $b): int => strcmp($a->destination, $b->destination));

        return $actions;
    }

    /**
     * Carry out a plan, and record what was written.
     *
     * Conflicts and skips are passed over rather than raised: the report the
     * caller already printed is where the user learns about them, and stopping
     * on the first would make one hand-edited config file block the other nine
     * files a package ships.
     *
     * @param InstallAction[] $actions
     *
     * @return InstallAction[] Those actually written.
     *
     * @throws InstallerException
     */
    public function apply(array $actions): array
    {
        $written = [];

        foreach ($actions as $action) {
            if (!$action->writes()) {
                continue;
            }

            $contents = $action->contents ?? $this->read($action->source);

            $this->write($action->destination, $contents);
            $this->receipt->record($this->package, $this->relative($action->destination), $contents);

            $written[] = $action;
        }

        if ($written !== []) {
            $this->receipt->write();
        }

        return $written;
    }

    /**
     * A plain copy: everything that is not a migration or a merged config file.
     */
    protected function planCopy(string $file): InstallAction
    {
        $destination = $this->root . '/' . $this->relativeToSource($file);
        $contents = $this->read($file);

        return $this->settle($file, $destination, $contents, null);
    }

    /**
     * A config file whose contents are spliced into an existing one.
     */
    protected function planMerge(string $file): InstallAction
    {
        $relative = $this->relativeToSource($file);
        $destination = $this->root . '/' . $this->stripMergePrefix($relative);
        $addition = $this->read($file);

        $existing = is_file($destination) ? $this->read($destination) : $this->scaffold();

        if (!$this->hasMarker($existing)) {
            return new InstallAction(
                InstallAction::CONFLICT,
                $file,
                $destination,
                'no "' . self::MERGE_MARKER . '" marker to merge into'
            );
        }

        if (str_contains($this->normalize($existing), $this->normalize($addition))) {
            return new InstallAction(InstallAction::SKIP, $file, $destination, 'already merged');
        }

        return new InstallAction(
            InstallAction::MERGE,
            $file,
            $destination,
            is_file($destination) ? 'merge into existing' : 'create and merge',
            $this->splice($existing, $addition)
        );
    }

    /**
     * A phinx migration: renamed and its class rewritten so two packages
     * cannot collide on a version number.
     */
    protected function planMigration(string $file): InstallAction
    {
        $name = basename($file);
        $slug = $this->slug();

        if (!preg_match('/^(\d+)_([A-Za-z0-9_]+)\.php$/', $name, $parts)) {
            return new InstallAction(
                InstallAction::CONFLICT,
                $file,
                $this->root . '/database/migrations/' . $name,
                'not a phinx migration filename (expected <version>_<name>.php)'
            );
        }

        [, $version, $stem] = $parts;

        // a package that already prefixed its own migration is left alone
        // rather than becoming orange_acl_orange_acl_...
        $prefixed = str_starts_with($stem, $slug . '_') ? $stem : $slug . '_' . $stem;
        $destination = $this->root . '/database/migrations/' . $version . '_' . $prefixed . '.php';

        $contents = $this->read($file);
        $found = $this->migrationClass($contents);

        if ($found === null) {
            return new InstallAction(
                InstallAction::CONFLICT,
                $file,
                $destination,
                'expected exactly one class extending AbstractMigration'
            );
        }

        // phinx derives the class from the filename, so a source file that
        // already disagrees is broken on its own terms - say that, rather than
        // renaming it into a differently broken state
        if ($found !== $this->studly($stem)) {
            return new InstallAction(
                InstallAction::CONFLICT,
                $file,
                $destination,
                'class ' . $found . ' does not match its filename (phinx expects ' . $this->studly($stem) . ')'
            );
        }

        // the same migration already here under another version number
        foreach ($this->existingMigrations() as $installed) {
            if (basename($installed) !== basename($destination) && str_contains($installed, '_' . $prefixed . '.php')) {
                return new InstallAction(
                    InstallAction::SKIP,
                    $file,
                    $destination,
                    'already installed as ' . basename($installed)
                );
            }
        }

        $renamed = $this->renameClass($contents, $found, $this->studly($prefixed));

        return $this->settle($file, $destination, $renamed, $renamed);
    }

    /**
     * Decide between copy, skip and conflict for a destination.
     *
     * The receipt is what makes this more than "does the file exist": a file
     * this package wrote and nobody has touched is a silent skip, while one
     * that differs from what was recorded is the user's own edit and must not
     * disappear without them asking for it.
     */
    protected function settle(string $source, string $destination, string $contents, ?string $carry): InstallAction
    {
        if (!file_exists($destination)) {
            return new InstallAction(InstallAction::COPY, $source, $destination, 'new', $carry);
        }

        $onDisk = $this->read($destination);

        if ($this->normalize($onDisk) === $this->normalize($contents)) {
            return new InstallAction(InstallAction::SKIP, $source, $destination, 'identical', $carry);
        }

        $relative = $this->relative($destination);

        if ($this->overwrite) {
            return new InstallAction(InstallAction::COPY, $source, $destination, 'overwriting', $carry);
        }

        if ($this->receipt->isUnchanged($this->package, $relative, $onDisk)) {
            // ours, untouched, but the package now ships something different -
            // an upgrade, which is exactly what -o is for
            return new InstallAction(
                InstallAction::CONFLICT,
                $source,
                $destination,
                'installed version differs from the one this package now ships (-o to update)',
                $carry
            );
        }

        if ($this->receipt->knows($this->package, $relative)) {
            return new InstallAction(
                InstallAction::CONFLICT,
                $source,
                $destination,
                'edited since it was installed (-o to discard those edits)',
                $carry
            );
        }

        return new InstallAction(
            InstallAction::CONFLICT,
            $source,
            $destination,
            'already exists and was not installed by this package (-o to replace)',
            $carry
        );
    }

    /**
     * The single class extending AbstractMigration, or null when it is not
     * exactly one.
     *
     * Deliberately strict. A file with two of them, or none, is something this
     * installer has no safe rename for, and guessing produces a migration phinx
     * will refuse at the least helpful possible moment.
     */
    protected function migrationClass(string $contents): ?string
    {
        $pattern = '/\bclass\s+([A-Za-z_][A-Za-z0-9_]*)\s+extends\s+[\\\\A-Za-z0-9_]*AbstractMigration\b/';

        if (preg_match_all($pattern, $contents, $matches) !== 1) {
            return null;
        }

        return $matches[1][0];
    }

    protected function renameClass(string $contents, string $from, string $to): string
    {
        $renamed = preg_replace(
            '/\bclass\s+' . preg_quote($from, '/') . '\b/',
            'class ' . $to,
            $contents,
            1
        );

        return $renamed ?? $contents;
    }

    /**
     * @return string[]
     */
    protected function existingMigrations(): array
    {
        $found = glob($this->root . '/database/migrations/*.php');

        return $found === false ? [] : $found;
    }

    /**
     * Every file under a directory, recursively, as absolute paths.
     *
     * @return string[]
     */
    protected function filesIn(string $directory): array
    {
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile()) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    protected function isMergeFile(string $file): bool
    {
        return str_starts_with(basename($file), self::MERGE_PREFIX);
    }

    protected function stripMergePrefix(string $relative): string
    {
        return str_replace('/' . self::MERGE_PREFIX, '/', '/' . ltrim($relative, '/'));
    }

    protected function relativeToSource(string $file): string
    {
        return ltrim(substr($file, strlen($this->source)), '/');
    }

    protected function relative(string $absolute): string
    {
        return ltrim(str_replace($this->root, '', $absolute), '/');
    }

    protected function hasMarker(string $contents): bool
    {
        return array_any(explode("\n", $contents), fn($line) => trim($line) === self::MERGE_MARKER);
    }

    protected function splice(string $contents, string $addition): string
    {
        $out = '';
        $done = false;

        foreach (explode("\n", $contents) as $index => $line) {
            $out .= ($index > 0 ? "\n" : '') . $line;

            if (!$done && trim($line) === self::MERGE_MARKER) {
                $out .= "\n" . $addition;
                $done = true;
            }
        }

        return $out;
    }

    /**
     * The file a merged config file is spliced into when there isn't one yet.
     */
    protected function scaffold(): string
    {
        return '<?php' . PHP_EOL
            . PHP_EOL
            . 'declare(strict_types=1);' . PHP_EOL
            . PHP_EOL
            . 'return [' . PHP_EOL
            . '    ' . self::MERGE_MARKER . PHP_EOL
            . '];' . PHP_EOL;
    }

    /**
     * 'orange/acl' -> 'orange_acl'
     */
    protected function slug(): string
    {
        $slug = preg_replace('/[^a-z0-9]+/', '_', strtolower($this->package));

        return trim($slug ?? '', '_');
    }

    /**
     * 'orange_acl_create_acl_tables' -> 'OrangeAclCreateAclTables'
     */
    protected function studly(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
    }

    /**
     * Comparison that ignores whitespace and line endings.
     *
     * A file re-indented on checkout, or written with CRLF, is not a user edit,
     * and reporting it as a conflict would give the user nothing to act on.
     */
    protected function normalize(string $input): string
    {
        return preg_replace('/\s+/', '', $input) ?? $input;
    }

    /**
     * @throws InstallerException
     */
    protected function read(string $path): string
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new InstallerException('Could not read "' . $path . '".');
        }

        return $contents;
    }

    /**
     * @throws InstallerException
     */
    protected function write(string $path, string $contents): void
    {
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new InstallerException('Could not create "' . $directory . '".');
        }

        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new InstallerException('Could not write "' . $path . '".');
        }
    }
}
