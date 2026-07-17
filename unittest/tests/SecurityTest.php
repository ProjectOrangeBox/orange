<?php

declare(strict_types=1);

use orange\framework\Security;

final class SecurityTest extends UnitTestHelper
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

    public function testCleanFilename(): void
    {
        $input = '';

        for ($c = 0; $c < 256; $c++) {
            $input .= chr($c);
        }

        $this->assertEquals(' ()+,-.0123456789<=>@ABCDEFGHIJKLMNOPQRSTUVWXYZ[]_abcdefghijklmnopqrstuvwxyz{|}~', $this->instance->cleanFilename($input));
        $this->assertEquals('This is a test 2004-10-31 103100', $this->instance->cleanFilename('This is a test 2004-10-31 10:31:00'));
        $this->assertEquals('This is a test <2004-10-31 103100>', $this->instance->cleanFilename('This is a test <2004-10-31 10:31:00>'));
    }

    public function testPasswordHash(): void
    {
        $password = 'MyDogHasFleas&12Bees';

        $savedInDb = $this->instance->encodePassword($password);

        $this->assertTrue($this->instance->verifyPassword($savedInDb, $password));
        $this->assertFalse($this->instance->verifyPassword($savedInDb, 'foobar'));
    }
}
