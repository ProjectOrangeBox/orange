# 12. Security

[← Logging & error handling](11-logging-and-errors.md) · [Manual index](README.md) · [Next: Global helpers →](13-helpers.md)

The `security` service is a small, libsodium‑backed toolkit for the cryptographic tasks a web app
actually needs: encrypting data, signing and verifying messages, hashing passwords, and
sanitising untrusted strings. It uses modern primitives with safe defaults and zeroes sensitive
buffers after use.

> **Requires ext‑sodium.** The service is only needed if you use it; if you do, PHP's `sodium`
> extension must be available (bundled with PHP 8).

## Getting the service

`orange\framework\Security` implements `SecurityInterface`. Inject it or pull it from the
container:

```php
#[AttachService('security')] protected SecurityInterface $security;
// or
$security = container()->security;
```

## Keys

Encryption and signing need keys. `createKeys()` generates them and writes them to the file paths
configured in `config/security.php`:

```php
$security->createKeys();   // generate + persist keys, returns bool
```

It creates an X25519 keypair (for encryption) plus an HMAC auth key (for signatures). Key files
are written `chmod 0600` (owner read/write only), optionally further owner‑restricted. Configure
where they live:

```php
// config/security.php
return [
    'public key'  => __ROOT__ . '/var/keys/public.key',
    'private key' => __ROOT__ . '/var/keys/private.key',
    'auth key'    => __ROOT__ . '/var/keys/auth.key',
];
```

Generate keys once (e.g. a one‑off CLI command), keep them out of version control, and restrict
their permissions. Never commit key files.

## Encryption

`encrypt()` / `decrypt()` use `crypto_box_seal` — anonymous public‑key encryption. Anyone with the
public key can encrypt; only the holder of the private key can decrypt:

```php
$sealed = $security->encrypt('sensitive data');   // ciphertext (safe to store/transmit)
$plain  = $security->decrypt($sealed);            // 'sensitive data'
```

Use this for data at rest you need to read back, not for passwords (see below).

## Signatures

`createSignature()` / `verifySignature()` use HMAC to detect tampering. Sign a message, and later
verify a message + signature match:

```php
$sig = $security->createSignature($payload);

// later, on the way back in:
if (!$security->verifySignature($sig, $payload)) {
    show403('Signature mismatch');
}
```

Note the argument order for verification: `verifySignature(string $hmac, string $data)` — signature
first, then the data it should cover. Verification is constant‑time.

## Passwords

Passwords are **hashed**, never encrypted. `encodePassword()` uses Argon2
(`sodium_crypto_pwhash_str`); `verifyPassword()` checks a candidate against a stored hash:

```php
// on sign-up / password change
$hash = $security->encodePassword($plaintextPassword);   // store $hash

// on login
if ($security->verifyPassword($storedHash, $userEntered)) {
    // authenticated
}
```

Argument order for verification: `verifyPassword(string $hash, string $userEntered)` — the stored
hash first, then what the user typed. Store the hash; never store or log the plaintext.

## Sanitising untrusted input

Two helpers clean hostile strings:

```php
// strip invisible / control characters that hide malicious payloads
$clean = $security->removeInvisibleCharacters($userInput);

// make a user-supplied filename safe to use on disk
$safe = $security->cleanFilename($uploadedName);
```

Use `cleanFilename()` before writing any user‑provided filename to the filesystem, and
`removeInvisibleCharacters()` when a string will be interpreted somewhere it could be smuggled
through (headers, commands, etc.).

## Choosing the right tool

| You need to… | Use |
|--------------|-----|
| Store data you must read back later | `encrypt()` / `decrypt()` |
| Detect tampering of a message you send out | `createSignature()` / `verifySignature()` |
| Store a password | `encodePassword()` / `verifyPassword()` |
| Sanitise a filename before writing it | `cleanFilename()` |
| Strip hidden characters from input | `removeInvisibleCharacters()` |

## A note on the other layers

Security isn't only this service. Elsewhere the framework already helps:

- **Output escaping** — use `e()`/`esc()` in views to prevent XSS ([Views](06-views.md)).
- **Open‑redirect protection** — `output`'s `force https`/redirects check the `allowed hosts`
  allowlist ([Request & response](09-request-and-response.md)).
- **Immutable request** — `input` is a read‑only snapshot with superglobals unset
  ([Request & response](09-request-and-response.md)).
- **JSON encoding flags** — `JsonController` encodes with hex‑escaping flags that neutralise
  `<`, `>`, `&`, `'`, `"` in JSON ([Controllers](05-controllers.md)).

## Summary

- `security` wraps libsodium: `encrypt`/`decrypt` (sealed boxes), `createSignature`/
  `verifySignature` (HMAC), `encodePassword`/`verifyPassword` (Argon2).
- Generate keys once with `createKeys()`; store them `0600`, out of version control.
- Sanitise filenames with `cleanFilename()` and strip hidden characters with
  `removeInvisibleCharacters()`.
- Hash passwords, encrypt data — never the other way around.

---

[← Logging & error handling](11-logging-and-errors.md) · [Manual index](README.md) · [Next: Global helpers →](13-helpers.md)
