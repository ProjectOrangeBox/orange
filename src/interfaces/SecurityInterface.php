<?php

declare(strict_types=1);

namespace orange\framework\interfaces;

/**
 * The framework's cryptographic primitives, behind names that say what they are
 * for rather than which algorithm they use.
 *
 * Four unrelated jobs share this contract because they share the key material
 * and the rule that no caller should be choosing a cipher: sealed-box
 * encryption, HMAC signing, password hashing, and two input sanitizers. The
 * concrete service is libsodium-backed, and which primitive backs which method
 * is an implementation detail deliberately kept out of the signatures - the
 * point of the seam is that it can be changed without touching callers.
 *
 * The three pairs are each other's inverse, and the verify halves are
 * constant-time. Note the argument order puts the stored value first:
 *
 *   verifySignature($hmac, $data)         not ($data, $hmac)
 *   verifyPassword($hash, $userEntered)   not ($userEntered, $hash)
 *
 * encrypt()/decrypt() are asymmetric and split across the keypair: encrypt()
 * needs only the public key, decrypt() the private one. So a process that only
 * has to encrypt need not hold the secret.
 *
 * Keys live in files named by config and createKeys() generates them. It will
 * not overwrite an existing key - regenerating means deliberately removing the
 * old files first, since doing it silently would strand every value already
 * encrypted or signed under them.
 *
 * The two sanitizers are not cryptography and are the odd members here.
 * removeInvisibleCharacters() strips control and format characters while
 * preserving printable unicode; cleanFilename() reduces a name to one safe
 * filename by allowlist, discarding any directory part, so it defuses traversal
 * rather than detecting it.
 */
interface SecurityInterface
{
    public function createKeys(): bool;

    public function encrypt(string $data): string;
    public function decrypt(string $data): string;

    public function createSignature(string $text): string;
    public function verifySignature(string $hmac, string $data): bool;

    public function encodePassword(string $password): string;
    public function verifyPassword(string $hash, string $userEntered): bool;

    public function removeInvisibleCharacters(string $string): string;
    public function cleanFilename(string $filename): string;
}
