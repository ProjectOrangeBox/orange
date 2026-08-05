<?php

declare(strict_types=1);

use orange\framework\exceptions\InstallerException;
use orange\framework\installer\InstallAction;
use orange\framework\installer\Manifest;
use orange\framework\installer\ModuleInstaller;
use orange\framework\installer\Receipt;

final class ModuleInstallerTest extends unitTestHelper
{
    protected string $root = '';
    protected string $package = '';

    protected function setUp(): void
    {
        $this->root = $this->makeTempDir('orange-installer-root-');
        $this->package = $this->makeTempDir('orange-installer-pkg-');
    }

    protected function tearDown(): void
    {
        $this->removeTempDir($this->root);
        $this->removeTempDir($this->package);
    }

    /* helpers */

    protected function ship(string $relative, string $contents): void
    {
        $path = $this->package . '/install/' . $relative;

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, $contents);
    }

    protected function place(string $relative, string $contents): void
    {
        $path = $this->root . '/' . $relative;

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, $contents);
    }

    protected function installer(bool $overwrite = false, string $name = 'orange/acl'): ModuleInstaller
    {
        return new ModuleInstaller($this->root, $this->package . '/install', $name, $overwrite);
    }

    protected function at(string $relative): string
    {
        return (string) file_get_contents($this->root . '/' . $relative);
    }

    /** @param InstallAction[] $actions */
    protected function forDestination(array $actions, string $relative): InstallAction
    {
        foreach ($actions as $action) {
            if (str_ends_with($action->destination, '/' . ltrim($relative, '/'))) {
                return $action;
            }
        }

        $this->fail('No action planned for "' . $relative . '".');
    }

    protected function migration(string $class): string
    {
        return '<?php' . PHP_EOL . PHP_EOL
            . 'use Phinx\Migration\AbstractMigration;' . PHP_EOL . PHP_EOL
            . 'final class ' . $class . ' extends AbstractMigration' . PHP_EOL
            . '{' . PHP_EOL . '    public function change(): void {}' . PHP_EOL . '}' . PHP_EOL;
    }

    /* construction */

    public function testAMissingInstallDirectoryIsRefused(): void
    {
        $this->expectException(InstallerException::class);

        new ModuleInstaller($this->root, $this->package . '/nope', 'orange/acl');
    }

    public function testNothingIsPlannedForAnEmptyInstallDirectory(): void
    {
        mkdir($this->package . '/install', 0777, true);

        $this->assertSame([], $this->installer()->plan());
    }

    /* plain copies */

    public function testAFileIsCopiedIntoItsMirroredPlace(): void
    {
        $this->ship('htdocs/css/acl.css', '.acl{}');

        $installer = $this->installer();
        $installer->apply($installer->plan());

        $this->assertSame('.acl{}', $this->at('htdocs/css/acl.css'));
    }

    public function testDirectoriesOutsideTheAllowedListAreIgnored(): void
    {
        $this->ship('application/controllers/Evil.php', '<?php');
        $this->ship('phinx.php', '<?php');

        $this->assertSame([], $this->installer()->plan());
        $this->assertFileDoesNotExist($this->root . '/application/controllers/Evil.php');
    }

    public function testAnIdenticalFileIsSkipped(): void
    {
        $this->ship('bin/tool', 'echo hi');
        $this->place('bin/tool', 'echo hi');

        $action = $this->forDestination($this->installer()->plan(), 'bin/tool');

        $this->assertSame(InstallAction::SKIP, $action->type);
        $this->assertSame('identical', $action->reason);
    }

    public function testAnUnrelatedExistingFileIsRefusedNotClobbered(): void
    {
        $this->ship('bin/tool', 'new');
        $this->place('bin/tool', 'mine');

        $installer = $this->installer();
        $action = $this->forDestination($installer->plan(), 'bin/tool');

        $this->assertSame(InstallAction::CONFLICT, $action->type);

        $installer->apply($installer->plan());

        $this->assertSame('mine', $this->at('bin/tool'));
    }

    public function testOverwriteReplacesAConflict(): void
    {
        $this->ship('bin/tool', 'new');
        $this->place('bin/tool', 'mine');

        $installer = $this->installer(true);
        $installer->apply($installer->plan());

        $this->assertSame('new', $this->at('bin/tool'));
    }

    /* the receipt */

    public function testAReceiptIsWrittenForWhatWasInstalled(): void
    {
        $this->ship('htdocs/css/acl.css', '.acl{}');

        $installer = $this->installer();
        $installer->apply($installer->plan());

        $receipt = json_decode((string) file_get_contents($this->root . Receipt::PATH), true);

        $this->assertIsArray($receipt);
        $this->assertArrayHasKey('orange/acl', $receipt);
        $this->assertArrayHasKey('htdocs/css/acl.css', $receipt['orange/acl']['files']);
    }

    public function testAnUpgradeIsDistinguishedFromAUserEdit(): void
    {
        $this->ship('htdocs/css/acl.css', '.acl{}');

        $first = $this->installer();
        $first->apply($first->plan());

        // the package now ships something different; the file on disk is still ours
        $this->ship('htdocs/css/acl.css', '.acl{color:red}');

        $action = $this->forDestination($this->installer()->plan(), 'htdocs/css/acl.css');

        $this->assertSame(InstallAction::CONFLICT, $action->type);
        $this->assertStringContainsString('this package now ships', $action->reason);
    }

    public function testAFileEditedAfterInstallIsReportedAsSuch(): void
    {
        $this->ship('htdocs/css/acl.css', '.acl{}');

        $first = $this->installer();
        $first->apply($first->plan());

        $this->place('htdocs/css/acl.css', '.acl{color:blue} /* mine */');
        $this->ship('htdocs/css/acl.css', '.acl{color:red}');

        $action = $this->forDestination($this->installer()->plan(), 'htdocs/css/acl.css');

        $this->assertSame(InstallAction::CONFLICT, $action->type);
        $this->assertStringContainsString('edited since it was installed', $action->reason);
    }

    public function testReinstallingIsASilentSkip(): void
    {
        $this->ship('htdocs/css/acl.css', '.acl{}');

        $first = $this->installer();
        $first->apply($first->plan());

        $action = $this->forDestination($this->installer()->plan(), 'htdocs/css/acl.css');

        $this->assertSame(InstallAction::SKIP, $action->type);
    }

    /* migrations */

    public function testAMigrationIsPrefixedWithThePackageName(): void
    {
        $this->ship('database/migrations/20260801000001_create_acl_tables.php', $this->migration('CreateAclTables'));

        $installer = $this->installer();
        $installer->apply($installer->plan());

        $expected = 'database/migrations/20260801000001_orange_acl_create_acl_tables.php';

        $this->assertFileExists($this->root . '/' . $expected);
        $this->assertStringContainsString('class OrangeAclCreateAclTables extends AbstractMigration', $this->at($expected));
        $this->assertStringNotContainsString('class CreateAclTables', $this->at($expected));
    }

    public function testTheVersionNumberIsPreserved(): void
    {
        $this->ship('database/migrations/20260801000001_create_acl_tables.php', $this->migration('CreateAclTables'));

        $action = $this->installer()->plan()[0];

        $this->assertStringContainsString('/20260801000001_', $action->destination);
    }

    public function testTwoPackagesSharingAVersionDoNotCollide(): void
    {
        $this->ship('database/migrations/20260801000001_create_tables.php', $this->migration('CreateTables'));

        $acl = $this->installer(false, 'orange/acl');
        $acl->apply($acl->plan());

        $blog = $this->installer(false, 'acme/blog');
        $blog->apply($blog->plan());

        $this->assertFileExists($this->root . '/database/migrations/20260801000001_orange_acl_create_tables.php');
        $this->assertFileExists($this->root . '/database/migrations/20260801000001_acme_blog_create_tables.php');
    }

    public function testAnAlreadyPrefixedMigrationIsNotPrefixedTwice(): void
    {
        $this->ship(
            'database/migrations/20260801000001_orange_acl_create_tables.php',
            $this->migration('OrangeAclCreateTables')
        );

        $action = $this->installer()->plan()[0];

        $this->assertStringEndsWith('20260801000001_orange_acl_create_tables.php', $action->destination);
    }

    public function testAMigrationWhoseClassDoesNotMatchItsFilenameIsRefused(): void
    {
        $this->ship('database/migrations/20260801000001_create_acl_tables.php', $this->migration('SomethingElse'));

        $action = $this->installer()->plan()[0];

        $this->assertSame(InstallAction::CONFLICT, $action->type);
        $this->assertStringContainsString('does not match its filename', $action->reason);
    }

    public function testAMigrationWithNoRecognizableClassIsRefused(): void
    {
        $this->ship('database/migrations/20260801000001_create_acl_tables.php', '<?php // nothing here');

        $action = $this->installer()->plan()[0];

        $this->assertSame(InstallAction::CONFLICT, $action->type);
        $this->assertStringContainsString('exactly one class', $action->reason);
    }

    public function testAMigrationWithoutAVersionPrefixIsRefused(): void
    {
        $this->ship('database/migrations/create_acl_tables.php', $this->migration('CreateAclTables'));

        $action = $this->installer()->plan()[0];

        $this->assertSame(InstallAction::CONFLICT, $action->type);
        $this->assertStringContainsString('not a phinx migration filename', $action->reason);
    }

    public function testAMigrationAlreadyInstalledUnderAnotherVersionIsSkipped(): void
    {
        $this->ship('database/migrations/20260801000001_create_acl_tables.php', $this->migration('CreateAclTables'));
        $this->place(
            'database/migrations/20260101000009_orange_acl_create_acl_tables.php',
            $this->migration('OrangeAclCreateAclTables')
        );

        $action = $this->installer()->plan()[0];

        $this->assertSame(InstallAction::SKIP, $action->type);
        $this->assertStringContainsString('already installed as', $action->reason);
    }

    /* seeds */

    public function testASeedIsCopiedVerbatim(): void
    {
        $seed = '<?php class AclSeeder extends AbstractSeed {}';

        $this->ship('database/seeds/AclSeeder.php', $seed);

        $installer = $this->installer();
        $installer->apply($installer->plan());

        $this->assertSame($seed, $this->at('database/seeds/AclSeeder.php'));
    }

    /* config merging */

    public function testAMergedConfigFileIsSplicedAfterTheMarker(): void
    {
        $this->ship('config/@acl.php', "    'acl' => true,");
        $this->place('config/acl.php', "<?php\n\nreturn [\n    " . ModuleInstaller::MERGE_MARKER . "\n];\n");

        $installer = $this->installer();
        $installer->apply($installer->plan());

        $merged = $this->at('config/acl.php');

        $this->assertStringContainsString("'acl' => true,", $merged);
        $this->assertStringContainsString(ModuleInstaller::MERGE_MARKER, $merged);
    }

    public function testAMergedConfigFileIsCreatedWhenAbsent(): void
    {
        $this->ship('config/@acl.php', "    'acl' => true,");

        $installer = $this->installer();
        $installer->apply($installer->plan());

        $this->assertFileExists($this->root . '/config/acl.php');
        $this->assertStringContainsString("'acl' => true,", $this->at('config/acl.php'));
    }

    public function testMergingTwiceDoesNotDuplicateTheContent(): void
    {
        $this->ship('config/@acl.php', "    'acl' => true,");

        $first = $this->installer();
        $first->apply($first->plan());

        $second = $this->installer();
        $action = $this->forDestination($second->plan(), 'config/acl.php');

        $this->assertSame(InstallAction::SKIP, $action->type);
        $this->assertSame('already merged', $action->reason);

        $second->apply($second->plan());

        $this->assertSame(1, substr_count($this->at('config/acl.php'), "'acl' => true,"));
    }

    public function testAConfigFileWithoutTheMarkerIsRefused(): void
    {
        $this->ship('config/@acl.php', "    'acl' => true,");
        $this->place('config/acl.php', "<?php\n\nreturn [];\n");

        $action = $this->forDestination($this->installer()->plan(), 'config/acl.php');

        $this->assertSame(InstallAction::CONFLICT, $action->type);
        $this->assertStringContainsString('marker', $action->reason);
    }

    public function testAnUnprefixedConfigFileIsCopiedNotMerged(): void
    {
        $this->ship('config/aclRoles.php', '<?php return [];');

        $action = $this->forDestination($this->installer()->plan(), 'config/aclRoles.php');

        $this->assertSame(InstallAction::COPY, $action->type);
    }

    public function testConfigIsOnlyPlannedOnce(): void
    {
        $this->ship('config/@acl.php', "    'acl' => true,");

        $this->assertCount(1, $this->installer()->plan());
    }

    /* the manifest */

    public function testAnAbsentManifestIsFine(): void
    {
        mkdir($this->package . '/install', 0777, true);

        $manifest = $this->installer()->manifest();

        $this->assertSame([], $manifest->after);
        $this->assertSame([], $manifest->unmet());
    }

    public function testAManifestIsRead(): void
    {
        $this->ship('install.php', '<?php return ["name" => "orange/acl", "after" => ["Run: composer db:migrate"]];');

        $manifest = $this->installer()->manifest();

        $this->assertSame('orange/acl', $manifest->name);
        $this->assertSame(['Run: composer db:migrate'], $manifest->after);
    }

    public function testAnUnmetExtensionIsReported(): void
    {
        $this->ship('install.php', '<?php return ["requires" => ["a_totally_made_up_extension"]];');

        $unmet = $this->installer()->manifest()->unmet();

        $this->assertCount(1, $unmet);
        $this->assertStringContainsString('a_totally_made_up_extension', $unmet[0]);
    }

    public function testAnUnmetPhpVersionIsReported(): void
    {
        $this->ship('install.php', '<?php return ["php" => "99.0"];');

        $this->assertCount(1, $this->installer()->manifest()->unmet());
    }

    public function testAManifestThatDoesNotReturnAnArrayIsRefused(): void
    {
        $this->ship('install.php', '<?php return "nope";');

        $this->expectException(InstallerException::class);

        $this->installer();
    }

    public function testTheManifestIsNotItselfInstalled(): void
    {
        $this->ship('install.php', '<?php return [];');

        $this->assertSame([], $this->installer()->plan());
    }

    /* planning writes nothing */

    public function testPlanningAloneTouchesNothing(): void
    {
        $this->ship('htdocs/css/acl.css', '.acl{}');
        $this->ship('config/@acl.php', "    'acl' => true,");
        $this->ship('database/migrations/20260801000001_create_acl_tables.php', $this->migration('CreateAclTables'));

        $this->installer()->plan();

        $this->assertFileDoesNotExist($this->root . '/htdocs/css/acl.css');
        $this->assertFileDoesNotExist($this->root . '/config/acl.php');
        $this->assertFileDoesNotExist($this->root . Receipt::PATH);
        $this->assertSame([], glob($this->root . '/database/migrations/*.php') ?: []);
    }

    public function testApplyReturnsOnlyWhatItWrote(): void
    {
        $this->ship('bin/tool', 'new');
        $this->ship('htdocs/css/acl.css', '.acl{}');
        $this->place('bin/tool', 'mine');

        $installer = $this->installer();
        $written = $installer->apply($installer->plan());

        $this->assertCount(1, $written);
        $this->assertStringEndsWith('acl.css', $written[0]->destination);
    }

    /* Manifest value handling */

    public function testManifestNoneIsEmpty(): void
    {
        $manifest = Manifest::none();

        $this->assertSame('', $manifest->name);
        $this->assertSame([], $manifest->requires);
        $this->assertSame([], $manifest->after);
    }

    public function testAScalarManifestValueIsAcceptedAsAListOfOne(): void
    {
        $this->ship('install.php', '<?php return ["after" => "Run: composer db:migrate"];');

        $this->assertSame(['Run: composer db:migrate'], $this->installer()->manifest()->after);
    }
}
