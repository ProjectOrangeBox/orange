<?php

declare(strict_types=1);

namespace orange\framework;

use orange\framework\base\Singleton;
use orange\framework\exceptions\InvalidValue;
use orange\framework\interfaces\SecurityInterface;
use orange\framework\exceptions\config\ConfigNotFound;
use orange\framework\exceptions\filesystem\FileNotFound;
use orange\framework\exceptions\filesystem\FileAlreadyExists;
use orange\framework\exceptions\filesystem\DirectoryNotWritable;
use orange\framework\exceptions\security\Security as SecurityException;

/**
 * Overview of Security.php
 *
 * This file defines the Security class in the orange\framework namespace.
 * It is a singleton utility class that provides cryptographic and security-related operations to the framework.
 * It implements the SecurityInterface to ensure a consistent contract for all security features.
 *
 * ⸻
 *
 * 1. Core Purpose
 *  •   Manage and generate cryptographic keys.
 *  •   Provide secure encryption and decryption of data.
 *  •   Create and verify HMAC signatures.
 *  •   Handle secure password hashing and verification.
 *  •   Offer input sanitation utilities (filenames, invisible characters).
 *
 * It centralizes all critical cryptographic and security operations in one place.
 *
 * ⸻
 *
 * 2. Key Features
 *  1.  Key Management (createKeys, getKeyFilePath)
 *  •   Generates X25519 public/private key pairs for encryption.
 *  •   Generates an authentication key for HMAC.
 *  •   Validates configuration, ensures directories are writable, and prevents overwriting existing keys.
 *  2.  Encryption & Decryption (encrypt, decrypt)
 *  •   encrypt() → Uses the public key and sodium_crypto_box_seal to encrypt data.
 *  •   decrypt() → Uses the private key and sodium_crypto_box_seal_open to decrypt.
 *  •   Handles conversion to/from hexadecimal securely.
 *  3.  Message Authentication (createSignature, verifySignature)
 *  •   createSignature() → Generates HMAC signatures using a secret auth key.
 *  •   verifySignature() → Verifies message signatures with constant-time checks.
 *  •   Protects against tampering.
 *  4.  Password Handling (encodePassword, verifyPassword)
 *  •   Uses Argon2 (via sodium_crypto_pwhash_str) to hash passwords.
 *  •   Verifies entered passwords against stored hashes.
 *  •   Protects against brute-force attacks.
 *  5.  Input Sanitization (removeInvisibleCharacters, cleanFilename)
 *  •   Removes control and format characters from input, preserving printable Unicode.
 *  •   Reduces a name to a single safe filename by allowlist, discarding any directory part.
 *  •   Reduces the risk of injection or traversal attacks.
 *
 * ⸻
 *
 * 3. Security Practices
 *  •   Uses Libsodium for modern cryptography.
 *  •   Overwrites key material it reads from disk (sodium_memzero). Note this cannot
 *      extend to values passed IN: a by-value parameter can only be zeroed in this
 *      class's own copy, leaving the caller's string intact, so those calls are
 *      deliberately absent rather than misleading. Callers wipe their own secrets.
 *  •   Validates all inputs (hex checks, config paths).
 *  •   Throws descriptive exceptions for misconfiguration or invalid data.
 *
 * ⸻
 *
 * 4. Big Picture
 *
 * Security.php acts as the security backbone of the Orange framework.
 * It provides consistent, modern, and safe handling of cryptographic operations, authentication,
 * password storage, and input cleaning — ensuring that developers don’t have to reimplement these delicate tasks incorrectly.
 *
 *
 * @package orange\framework
 */
class Security extends Singleton implements SecurityInterface
{
    /**
     * Constructor for the Security class.
     *
     * This protected constructor enforces the Singleton pattern by preventing
     * direct instantiation. It initializes the configuration settings.
     *
     * @param array $config Security configuration settings.
     */
    protected function __construct(array $config)
    {
        logMsg('DEBUG', __METHOD__);

        $this->config = $config;
    }

    /**
     * Generates public, private, and authentication keys.
     *
     * Validates file paths, ensures directories are writable, and prevents
     * overwriting existing key files.
     *
     * @param bool $restrictOwnership When true (the default), the private key and auth
     *                                key files also have their ownership set to the
     *                                current process user/group, in addition to the
     *                                chmod(0600) that always applies. This closes a gap
     *                                where chmod(0600) alone only protects the file
     *                                against users other than whoever happens to OWN
     *                                it - if that owner isn't actually the current user
     *                                (e.g. an unexpected deploy/build step created the
     *                                file), the restriction wouldn't protect against the
     *                                right user. Requires the posix extension; silently
     *                                skipped when unavailable (e.g. Windows).
     * @return bool Returns true if all keys are successfully created.
     *
     * @throws ConfigNotFound If key paths are missing from the configuration.
     * @throws DirectoryNotWritable If the directories for keys are not writable.
     * @throws FileAlreadyExists If key files already exist.
     */
    public function createKeys(bool $restrictOwnership = true): bool
    {
        foreach (['public key', 'private key', 'auth key'] as $key) {
            if (!isset($this->config[$key])) {
                throw new ConfigNotFound($key);
            }
            if (!is_writable(dirname($this->config[$key]))) {
                throw new DirectoryNotWritable(dirname($this->config[$key]));
            }
            if (file_exists($this->config[$key])) {
                throw new FileAlreadyExists($this->config[$key]);
            }
        }

        // Generate an X25519 keypair for use with the sodium_crypto_box API
        $privateKey = sodium_crypto_box_keypair();

        // try to write the private and public keys
        // secret key material is restricted to owner read/write (0600) so it is not
        // exposed to other users via the umask default (often world-readable)
        $success1 = file_put_contents($this->config['private key'], $privateKey);
        chmod($this->config['private key'], 0600);
        if ($restrictOwnership) {
            $this->restrictToCurrentUser($this->config['private key']);
        }
        // Get an X25519 public key from an X25519 keypair
        $success2 = file_put_contents($this->config['public key'], sodium_crypto_box_publickey($privateKey));

        // Overwrite a string with NUL characters
        sodium_memzero($privateKey);

        // Get random bytes for key
        $authKey = sodium_crypto_auth_keygen();

        // write the auth key salt (also secret; restrict to owner)
        $success3 = file_put_contents($this->config['auth key'], $authKey);
        chmod($this->config['auth key'], 0600);
        if ($restrictOwnership) {
            $this->restrictToCurrentUser($this->config['auth key']);
        }

        // Overwrite a string with NUL characters
        sodium_memzero($authKey);

        return $success1 !== false && $success2 !== false && $success3 !== false;
    }

    /**
     * Best-effort: set a file's owner and group to the current process's
     * effective user/group, so a chmod(0600) alongside it actually restricts
     * access to whoever is running this code right now - not just whoever
     * happens to already own the file.
     *
     * Requires the posix extension (unavailable on Windows); a no-op if it's
     * missing. Ownership changes are silently ignored on failure (e.g. not
     * running as the file's current owner/root) since this is a hardening
     * best-effort, not a hard requirement - chmod(0600) has already run either way.
     *
     * @param string $file
     * @return void
     */
    protected function restrictToCurrentUser(string $file): void
    {
        if (function_exists('posix_geteuid') && function_exists('posix_getegid')) {
            @chown($file, posix_geteuid());
            @chgrp($file, posix_getegid());
        }
    }

    /**
     * Encrypts data using the public key.
     *
     * @param string $data Data to encrypt.
     * @return string Encrypted data in hexadecimal format.
     * @throws ConfigNotFound If the public key path is missing from the configuration.
     * @throws FileNotFound If the public key file does not exist.
     */
    public function encrypt(string $data): string
    {
        $key = file_get_contents($this->getKeyFilePath('public'));

        // Convert to hex without side-channels
        $encrypted = sodium_bin2hex(sodium_crypto_box_seal($data, $key));

        // Wipe the key material this method read. $data is deliberately not
        // wiped: it is a by-value parameter, so zeroing it would only clear this
        // function's own copy while the caller's string - the actual plaintext -
        // stays in memory untouched. Wiping a parameter reads as a guarantee
        // that isn't being made. A caller that needs its plaintext gone must
        // sodium_memzero() its own variable.
        sodium_memzero($key);

        return $encrypted;
    }

    /**
     * Decrypts encrypted data using the private key.
     *
     * @param string $data Encrypted data in hexadecimal format.
     * @return string Decrypted plain text data.
     *
     * @throws SecurityException If the data is not valid hexadecimal, or cannot be decrypted
     *         (e.g. forged, truncated, or encrypted for a different key).
     * @throws ConfigNotFound If the private key path is missing from the configuration.
     * @throws FileNotFound If the private key file does not exist.
     */
    public function decrypt(string $data): string
    {
        // Check for character(s) representing a hexadecimal digit
        if (!ctype_xdigit($data)) {
            throw new SecurityException('decrypt data argument invalid');
        }

        // Convert from hex without side-channels
        $data = sodium_hex2bin($data);

        // Get the private key
        $key = file_get_contents($this->getKeyFilePath('private'));

        // Anonymous public-key encryption (decrypt); returns false if the
        // ciphertext was forged, truncated, or encrypted for a different key
        $decrypt = sodium_crypto_box_seal_open($data, $key);

        // both are local: $key is secret key material, and $data was reassigned
        // above to a locally-decoded binary copy
        sodium_memzero($data);
        sodium_memzero($key);

        if ($decrypt === false) {
            throw new SecurityException('Unable to decrypt data.');
        }

        return $decrypt;
    }

    /**
     * Generates an HMAC signature for a given message.
     *
     * @param string $message The message to sign.
     * @return string HMAC signature in hexadecimal format.
     * @throws ConfigNotFound If the auth key path is missing from the configuration.
     * @throws FileNotFound If the auth key file does not exist.
     */
    public function createSignature(string $message): string
    {
        $key = file_get_contents($this->getKeyFilePath('auth'));

        // Convert to hex without side-channels
        $token = sodium_bin2hex(sodium_crypto_auth($message, $key));

        // only the key is wipeable here - $message is a by-value parameter, so
        // zeroing it would clear this copy and leave the caller's untouched
        sodium_memzero($key);

        return $token;
    }

    /**
     * Verifies an HMAC signature against a message.
     *
     * @param string $signature HMAC signature to verify.
     * @param string $message Original message.
     * @return bool True if the signature is valid, false otherwise.
     * @throws ConfigNotFound If the auth key path is missing from the configuration (only reached
     *         when $signature is valid hex of the expected length).
     * @throws FileNotFound If the auth key file does not exist (same condition as above).
     */
    public function verifySignature(string $signature, string $message): bool
    {
        $isValid = false;

        // Check for character(s) representing a hexadecimal digit
        if (ctype_xdigit($signature)) {
            // Convert from hex without side-channels
            $signature = sodium_hex2bin($signature);

            if (mb_strlen($signature, '8bit') === SODIUM_CRYPTO_AUTH_BYTES) {
                $key = file_get_contents($this->getKeyFilePath('auth'));

                // Secret-key message verification - HMAC SHA-512/256
                $isValid = sodium_crypto_auth_verify($signature, $message, $key);

                // secret key material this method read - worth wiping
                sodium_memzero($key);
            }
        }

        // $signature and $message are by-value parameters and are not secrets in
        // any case; wiping them here would only clear these copies, so it is left
        // out rather than implying a guarantee that isn't made.
        return $isValid;
    }

    /**
     * Hashes a password securely using Argon2.
     *
     * @param string $password Plain text password.
     * @return string Hashed password.
     */
    public function encodePassword(string $password): string
    {
        // Get a formatted password hash (for storage)
        $encoded = sodium_crypto_pwhash_str($password, SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE, SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE);

        // $password is a by-value parameter: zeroing it clears this copy only,
        // and the caller still holds the plaintext. A caller that wants it gone
        // must wipe its own variable - that is not something this method can do
        // on its behalf, and pretending otherwise is worse than not trying.
        return $encoded;
    }

    /**
     * Verifies a password against its hash.
     *
     * @param string $hash Password hash.
     * @param string $userEntered User-entered password.
     * @return bool True if the password is valid, false otherwise.
     */
    public function verifyPassword(string $hash, string $userEntered): bool
    {
        // Verify a password against a hash
        $isValid = sodium_crypto_pwhash_str_verify($hash, $userEntered);

        // both are by-value parameters - see encodePassword() for why they are
        // not wiped here
        return $isValid;
    }

    /**
     * Removes control and format characters from a string.
     *
     * Printable text survives intact whatever alphabet it is written in - this
     * used to strip everything outside \x20-\x7E, which quietly destroyed every
     * accented or non-Latin string handed to it ("résumé-Ünïcode.pdf" came back
     * as "rsum-ncode.pdf") in a framework that otherwise runs on UTF-8
     * throughout. What goes is the genuinely invisible: NULs and other C0/C1
     * controls, and the format characters (zero-width joiners, bidi overrides)
     * used to disguise what a string actually says.
     *
     * @param string $string Input string.
     * @return string Sanitized string.
     */
    public function removeInvisibleCharacters(string $string): string
    {
        // A string that isn't valid UTF-8 has no Unicode reading worth
        // preserving, and preg's /u mode refuses such a subject outright
        // (returning null) - so drop the invalid sequences first. The
        // substitute character is forced to 'none' rather than trusted from
        // ini, so a malformed byte is removed instead of becoming a literal '?'.
        if (!mb_check_encoding($string, 'UTF-8')) {
            $previousSubstitute = mb_substitute_character();
            mb_substitute_character('none');
            $string = mb_convert_encoding($string, 'UTF-8', 'UTF-8');
            mb_substitute_character($previousSubstitute);
        }

        // \p{Cc} control, \p{Cf} format. Removing characters in a class can
        // never produce another character in that class, so one pass is enough -
        // the old implementation looped because a substring replace can join two
        // halves into a new match, which a character class cannot.
        return preg_replace('/[\p{Cc}\p{Cf}]/u', '', $string) ?? '';
    }

    /**
     * Reduces a caller-supplied name to something safe to use as a single
     * filename.
     *
     * Works by allowlist. The denylist this replaced tried to enumerate the ways
     * a traversal can be written - '../', '%3c', '%2528' and twenty-odd more -
     * which is a game a denylist does not win: it had no entry for overlong or
     * double-encoded forms it hadn't thought of, and it stripped '%' everywhere
     * as a blunt approximation. Deciding what may stay is bounded in a way that
     * deciding what must go is not.
     *
     * Note this returns a *filename*, never a path: any directory component is
     * discarded rather than sanitized. It can return '' (a name with nothing
     * allowlistable in it), which callers must treat as "no usable name" rather
     * than writing to it.
     *
     * @param string $filename Input filename.
     * @return string Sanitized filename, possibly empty.
     */
    public function cleanFilename(string $filename): string
    {
        // First, because these are how a name sneaks past everything after them:
        // a NUL truncates the string in any C-level call downstream, and a bidi
        // override can make "evil.php" render to a human as "evil.gpj".
        $filename = $this->removeInvisibleCharacters($filename);

        // a backslash separates directories on Windows, and basename() on Linux
        // would not recognize it as one
        $filename = str_replace('\\', '/', $filename);

        // Drop every directory component. This is what actually defeats
        // traversal - not pattern-matching the spelling of it.
        $filename = basename($filename);

        // Unicode letters and digits (so "résumé.pdf" survives) plus the
        // punctuation filenames genuinely use.
        $filename = preg_replace('/[^\p{L}\p{N}._\- ]/u', '', $filename) ?? '';

        // collapse dot runs so nothing is left resembling a traversal segment
        $filename = preg_replace('/\.{2,}/', '.', $filename) ?? '';

        // A leading dot hides the file; Windows silently ignores trailing dots
        // and spaces, which would make "x.php." and "x.php" the same file there.
        return trim($filename, ' .');
    }

    /**
     * Retrieves the file path of a specified key type.
     *
     * @param string $which Key type: public, private, or auth.
     * @return string Path to the specified key.
     *
     * @throws InvalidValue
     * @throws ConfigNotFound
     * @throws FileNotFound
     */
    protected function getKeyFilePath(string $which): string
    {
        if (!in_array($which, ['public', 'private', 'auth'])) {
            throw new InvalidValue($which . ' is an unknown key file type.');
        }

        $configKey = $which . ' key';

        if (!isset($this->config[$configKey])) {
            throw new ConfigNotFound($configKey);
        }

        if (!file_exists($this->config[$configKey])) {
            throw new FileNotFound($this->config[$configKey]);
        }

        return $this->config[$configKey];
    }
}
