<?php

declare(strict_types=1);

use orange\framework\Security;
use orange\framework\exceptions\InvalidValue;
use orange\framework\exceptions\config\ConfigNotFound;
use orange\framework\exceptions\filesystem\FileNotFound;
use orange\framework\exceptions\filesystem\FileAlreadyExists;
use orange\framework\exceptions\filesystem\DirectoryNotWritable;
use orange\framework\exceptions\security\Security as SecurityException;

final class SecurityTest extends unitTestHelper
{
    protected $instance;

    private $publicKeyFile = WORKINGDIR . '/writeable/public.key';
    private $privateKeyFile = WORKINGDIR . '/writeable/private.key';
    private $authKeyFile = WORKINGDIR . '/writeable/auth.key';

    protected function setUp(): void
    {
        $this->tearDown();

        $this->instance = Security::getInstance([
            'public key' => $this->publicKeyFile,
            'private key' => $this->privateKeyFile,
            'auth key' => $this->authKeyFile,
        ]);

        $this->instance->createKeys();
    }

    protected function tearDown(): void
    {
        if (file_exists($this->publicKeyFile)) {
            unlink($this->publicKeyFile);
        }
        if (file_exists($this->privateKeyFile)) {
            unlink($this->privateKeyFile);
        }
        if (file_exists($this->authKeyFile)) {
            unlink($this->authKeyFile);
        }
    }

    public function createKeys(): void
    {
        // created in setup
        $this->assertFileExists($this->publicKeyFile);
        $this->assertFileExists($this->privateKeyFile);
        $this->assertFileExists($this->authKeyFile);
    }

    public function testCreateKeysSetsPrivateAndAuthKeyPermissionsToOwnerOnly(): void
    {
        $this->assertEquals(0600, fileperms($this->privateKeyFile) & 0777);
        $this->assertEquals(0600, fileperms($this->authKeyFile) & 0777);
    }

    public function testCreateKeysWithRestrictOwnershipFalseStillSucceeds(): void
    {
        $this->tearDown();

        $this->assertTrue($this->instance->createKeys(false));
        $this->assertFileExists($this->privateKeyFile);
        // chmod(0600) always applies regardless of the restrictOwnership flag
        $this->assertEquals(0600, fileperms($this->privateKeyFile) & 0777);
    }

    public function testCreateKeysRestrictsOwnershipToCurrentUser(): void
    {
        if (!function_exists('posix_geteuid') || !function_exists('posix_getegid')) {
            $this->markTestSkipped('posix extension not available');
        }

        $this->assertEquals(posix_geteuid(), fileowner($this->privateKeyFile));
        $this->assertEquals(posix_getegid(), filegroup($this->privateKeyFile));
        $this->assertEquals(posix_geteuid(), fileowner($this->authKeyFile));
        $this->assertEquals(posix_getegid(), filegroup($this->authKeyFile));
    }

    public function testEncrypt(): void
    {
        $text = 'Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry\'s standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised in the 1960s with the release of Letraset sheets containing Lorem Ipsum passages, and more recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum.';

        $this->assertEquals($text, $this->instance->decrypt($this->instance->encrypt($text)));
    }

    public function testAuth(): void
    {
        $text = 'Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry\'s standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised in the 1960s with the release of Letraset sheets containing Lorem Ipsum passages, and more recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum.';

        $sig = $this->instance->createSignature($text);

        $this->assertTrue($this->instance->verifySignature($sig, $text));
        $this->assertFalse($this->instance->verifySignature('', 'foobar'));
    }

    public function testRemoveInvisibleCharacters(): void
    {
        $input = '';

        for ($c = 0; $c < 256; $c++) {
            $input .= chr($c);
        }

        $this->assertEquals(' !"#$%&\'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\]^_`abcdefghijklmnopqrstuvwxyz{|}~', $this->instance->removeInvisibleCharacters($input));
    }

    /**
     * Printable text in any alphabet must survive - this used to strip
     * everything outside \x20-\x7E, which destroyed every accented or non-Latin
     * string it was given.
     */
    public function testRemoveInvisibleCharactersPreservesPrintableUnicode(): void
    {
        $this->assertEquals('résumé-Ünïcode.pdf', $this->instance->removeInvisibleCharacters('résumé-Ünïcode.pdf'));
        $this->assertEquals('日本語', $this->instance->removeInvisibleCharacters('日本語'));

        // but the genuinely invisible still goes: a NUL and a zero-width space
        $this->assertEquals('abc', $this->instance->removeInvisibleCharacters("a\x00b\u{200B}c"));
        // as does a right-to-left override, which can disguise an extension
        $this->assertEquals('evilphp', $this->instance->removeInvisibleCharacters("evil\u{202E}php"));
    }

    public function testCleanFilename(): void
    {
        $input = '';

        for ($c = 0; $c < 256; $c++) {
            $input .= chr($c);
        }

        // everything up to the last separator is a directory component and is
        // dropped; of what remains only the allowlisted characters survive
        $this->assertEquals('_abcdefghijklmnopqrstuvwxyz', $this->instance->cleanFilename($input));
        $this->assertEquals('This is a test 2004-10-31 103100', $this->instance->cleanFilename('This is a test 2004-10-31 10:31:00'));
        $this->assertEquals('This is a test 2004-10-31 103100', $this->instance->cleanFilename('This is a test <2004-10-31 10:31:00>'));
    }

    /**
     * Traversal is defeated by discarding the directory part outright, not by
     * recognizing how it was spelled - so encoded and Windows-style forms fall
     * out for free rather than needing their own denylist entries.
     */
    public function testCleanFilenameDiscardsAnyDirectoryComponent(): void
    {
        $this->assertEquals('passwd', $this->instance->cleanFilename('../../etc/passwd'));
        $this->assertEquals('report.pdf', $this->instance->cleanFilename('/absolute/path/report.pdf'));
        $this->assertEquals('cmd.exe', $this->instance->cleanFilename('..\\..\\windows\\system32\\cmd.exe'));
        $this->assertEquals('x', $this->instance->cleanFilename('....//....//x'));
    }

    public function testCleanFilenameKeepsUnicodeNamesAndNeutralizesTricks(): void
    {
        // a legitimate name is not collateral damage
        $this->assertEquals('résumé-Ünïcode.pdf', $this->instance->cleanFilename('résumé-Ünïcode.pdf'));

        // a NUL can no longer truncate the name at a C-level call downstream
        $this->assertEquals('evil.php.jpg', $this->instance->cleanFilename("evil.php\x00.jpg"));

        // no leading dot (hidden file), no dot runs, no trailing dot/space -
        // Windows would treat "x.php." and "x.php" as the same file
        $this->assertEquals('htaccess', $this->instance->cleanFilename('.htaccess'));
        $this->assertEquals('file.txt', $this->instance->cleanFilename('file...txt'));
        $this->assertEquals('spaced name .txt', $this->instance->cleanFilename('  spaced name .txt  '));

        // nothing allowlistable at all - callers must treat '' as "no usable name"
        $this->assertEquals('', $this->instance->cleanFilename('???'));
    }

    public function testPasswordHash(): void
    {
        $password = 'MyDogHasFleas&12Bees';

        $savedInDb = $this->instance->encodePassword($password);

        $this->assertTrue($this->instance->verifyPassword($savedInDb, $password));
        $this->assertFalse($this->instance->verifyPassword($savedInDb, 'foobar'));
    }

    public function testCreateKeysThrowsConfigNotFoundWhenKeyPathMissing(): void
    {
        $this->tearDown();

        $instance = Security::newInstance([
            'public key' => $this->publicKeyFile,
            // 'private key' intentionally omitted
            'auth key' => $this->authKeyFile,
        ]);

        $this->expectException(ConfigNotFound::class);

        $instance->createKeys();
    }

    public function testCreateKeysThrowsDirectoryNotWritableWhenDirectoryNotWritable(): void
    {
        $this->tearDown();

        $unwritableDir = WORKINGDIR . '/unwritablekeys';

        if (!is_dir($unwritableDir)) {
            mkdir($unwritableDir);
        }
        chmod($unwritableDir, 0555);

        $instance = Security::newInstance([
            'public key' => $unwritableDir . '/public.key',
            'private key' => $unwritableDir . '/private.key',
            'auth key' => $unwritableDir . '/auth.key',
        ]);

        try {
            $this->expectException(DirectoryNotWritable::class);

            $instance->createKeys();
        } finally {
            chmod($unwritableDir, 0755);
            rmdir($unwritableDir);
        }
    }

    public function testCreateKeysThrowsFileAlreadyExistsWhenKeyFileExists(): void
    {
        // setUp() already created all three key files via createKeys()
        $this->expectException(FileAlreadyExists::class);

        $this->instance->createKeys();
    }

    public function testDecryptThrowsOnNonHexData(): void
    {
        $this->expectException(SecurityException::class);

        $this->instance->decrypt('not-hex-data!!');
    }

    public function testDecryptThrowsOnForgedData(): void
    {
        $this->expectException(SecurityException::class);

        // valid hex, but not a real sealed box for our key
        $this->instance->decrypt(bin2hex('this is definitely not encrypted data'));
    }

    public function testGetKeyFilePathThrowsInvalidValueForUnknownType(): void
    {
        $this->expectException(InvalidValue::class);

        $this->callMethod('getKeyFilePath', ['bogus']);
    }

    public function testGetKeyFilePathThrowsConfigNotFoundWhenMissingFromConfig(): void
    {
        $this->tearDown();

        $instance = Security::newInstance([
            'public key' => $this->publicKeyFile,
            'private key' => $this->privateKeyFile,
            // 'auth key' intentionally omitted
        ]);

        $this->expectException(ConfigNotFound::class);

        $this->callMethod('getKeyFilePath', ['auth'], $instance);
    }

    public function testGetKeyFilePathThrowsFileNotFoundWhenFileMissing(): void
    {
        // key files exist from setUp(), but delete the public key file so its
        // path is still configured yet points at nothing on disk
        unlink($this->publicKeyFile);

        $this->expectException(FileNotFound::class);

        $this->callMethod('getKeyFilePath', ['public']);
    }
}
