# How to use the Installer

`vendor/bin/installModule` copies the files a composer package ships into the
application that installed it — migrations, seeds, config, assets, CLI scripts.

A composer package can put files in `vendor/`, but it cannot put them anywhere
your application actually reads from. A phinx migration has to be in
`database/migrations/`. A stylesheet has to be under `htdocs/`. Config has to be
in `config/`. Historically every package answered this with a paragraph in its
README saying "now copy these files", and every consumer did it by hand,
differently, and lost track of what came from where.

This is that copy, done once and recorded.

```sh
vendor/bin/installModule orange/acl
```

---

## Contents

- [For people installing a package](#for-people-installing-a-package)
- [For people writing a package](#for-people-writing-a-package)
- [Scenarios](#scenarios)
  - [1. A package that ships database tables](#1-a-package-that-ships-database-tables)
  - [2. A package that ships config](#2-a-package-that-ships-config)
  - [3. A package that ships front-end assets](#3-a-package-that-ships-front-end-assets)
  - [4. A package that ships a CLI script](#4-a-package-that-ships-a-cli-script)
  - [5. Everything at once — a full feature package](#5-everything-at-once--a-full-feature-package)
  - [6. Installing from inside your own application](#6-installing-from-inside-your-own-application)
  - [7. Upgrading when a package changes a file](#7-upgrading-when-a-package-changes-a-file)
- [Reference](#reference)
- [How it decides: copy, skip or refuse](#how-it-decides-copy-skip-or-refuse)
- [Migrations are renamed](#migrations-are-renamed)
- [Seeds are not renamed](#seeds-are-not-renamed)
- [The receipt](#the-receipt)
- [Design notes](#design-notes)

---

## For people installing a package

Look before you leap. `--dry-run` runs exactly the code a real install runs and
stops before writing anything:

```sh
vendor/bin/installModule orange/acl --dry-run
```

```text
Installing orange/acl from vendor/orange/acl/install
Dry run - nothing will be written.

  copy   database/migrations/20260801000001_orange_acl_create_acl_tables.php  (new)
  copy   database/seeds/OrangeAclSeeder.php  (new)

2 would be written, 0 skipped, 0 refused
```

Happy with that? Drop the flag:

```sh
vendor/bin/installModule orange/acl
```

```text
  copy   database/migrations/20260801000001_orange_acl_create_acl_tables.php  (new)
  copy   database/seeds/OrangeAclSeeder.php  (new)

2 written, 0 skipped, 0 refused

Next:
  composer db:migrate                 create the six acl tables
  composer db:seed -s OrangeAclSeeder create the guest user row acl requires
```

**Copying files is not installing.** That `Next:` block is the package telling
you what it cannot do for you. A migration that has been copied but not run has
created no tables.

Running it again is safe and says so:

```text
  skip   database/migrations/20260801000001_orange_acl_create_acl_tables.php  (identical)
  skip   database/seeds/OrangeAclSeeder.php  (identical)

0 written, 2 skipped, 0 refused
```

---

## For people writing a package

Put an `install/` directory at the root of your package. **Its contents mirror
the application root**, so where a file sits *is* where it goes. There is no
manifest of paths to keep in sync with the files, and a person reading your
package can predict where everything lands.

```text
vendor/acme/blog/
├── src/
├── composer.json
└── install/                         <- everything below is copied
    ├── install.php                  optional metadata (not itself copied)
    ├── config/@site.php             merged into  config/site.php
    ├── config/blog.php              copied to    config/blog.php
    ├── database/migrations/*.php    copied to    database/migrations/
    ├── database/seeds/*.php         copied to    database/seeds/
    ├── htdocs/css/blog.css          copied to    htdocs/css/blog.css
    ├── bin/blogimport               copied to    bin/blogimport
    ├── support/*                    copied to    support/
    └── var/*                        copied to    var/
```

Only those seven destinations are honoured:

| Destination           | For                                            |
| --------------------- | ---------------------------------------------- |
| `bin/`                | CLI scripts                                    |
| `config/`             | config files — copied, or merged with `@`      |
| `database/migrations/`| phinx migrations — **renamed**, see below      |
| `database/seeds/`     | phinx seeders — copied as named                |
| `htdocs/`             | anything web-reachable: css, js, images        |
| `support/`            | reference material — SQL, docs, templates      |
| `var/`                | writable working directories                   |

Anything else in `install/` is **silently ignored**. A package cannot write to
`application/`, cannot write to the repo root, and cannot escape upward with
`..`. That is the point of the mirror: if arbitrary destinations were allowed,
you could no longer predict where a package's files land by looking at it.

---

## Scenarios

### 1. A package that ships database tables

The common case, and the reason this exists.

```text
install/
├── install.php
├── database/migrations/20260801000001_create_blog_tables.php
└── database/seeds/AcmeBlogSeeder.php
```

`install.php`:

```php
<?php

return [
    'name' => 'acme/blog',
    'requires' => ['pdo_mysql'],
    'after' => [
        'composer db:migrate                  create the blog tables',
        'composer db:seed -s AcmeBlogSeeder   add the default categories',
    ],
];
```

Write the migration exactly as you would in an application — the class name
matching the filename, as phinx requires:

```php
// install/database/migrations/20260801000001_create_blog_tables.php
final class CreateBlogTables extends AbstractMigration
{
    public function change(): void
    {
        $this->table('blog_posts')->addColumn('title', 'string')->create();
    }
}
```

It arrives in the application renamed:

```text
database/migrations/20260801000001_acme_blog_create_blog_tables.php
final class AcmeBlogCreateBlogTables extends AbstractMigration
```

See [Migrations are renamed](#migrations-are-renamed) for why.

**Seeders should be idempotent.** Phinx keeps no record of a seeder having run —
unlike migrations — so yours can be run twice. Check before you insert:

```php
final class AcmeBlogSeeder extends AbstractSeed
{
    public function run(): void
    {
        $row = $this->fetchRow('select count(*) as found from `blog_categories`');

        if (is_array($row) && (int) $row['found'] > 0) {
            return;
        }

        $this->table('blog_categories')->insert([...])->save();
    }
}
```

**Ship only what your package cannot run without.** A row your code fails on is
part of installing you. A demo account with a published password is not —
someone will run your seeder without reading it, on a database that matters.

---

### 2. A package that ships config

Two ways, and the difference matters.

**A whole file you own** — just put it in `config/`:

```text
install/config/blog.php    ->    config/blog.php
```

**A fragment of a file the application owns** — prefix with `@` and it is
*merged* rather than copied:

```text
install/config/@site.php   ->    merged into config/site.php
```

The application's file needs a marker saying where merged content goes:

```php
<?php
// config/site.php

return [
    'title' => 'My Site',

    /* merged content below */
];
```

Your `install/config/@site.php` holds just the fragment — no `<?php`, no
`return`:

```php
    'blog' => ['posts_per_page' => 10],
```

After installing:

```php
return [
    'title' => 'My Site',

    /* merged content below */
    'blog' => ['posts_per_page' => 10],
];
```

If `config/site.php` does not exist, it is created around the marker for you.
If it exists but has **no marker**, the install refuses rather than guessing:

```text
  REFUSE  config/site.php  (no "/* merged content below */" marker to merge into)
```

Merging twice is a no-op — the content is compared ignoring whitespace, so
reformatting the target does not cause a duplicate:

```text
  skip   config/site.php  (already merged)
```

---

### 3. A package that ships front-end assets

```text
install/htdocs/css/blog.css        ->  htdocs/css/blog.css
install/htdocs/js/blog.js          ->  htdocs/js/blog.js
install/htdocs/images/blog/*.svg   ->  htdocs/images/blog/*.svg
```

Nested directories are created as needed. Namespace your files
(`htdocs/css/blog.css`, not `htdocs/css/style.css`) — you are writing into a
directory shared with the application and every other package.

Do not hardcode these paths in views. Add a route entry with a `name` and no
`callback`, and resolve it with `$router->getUrl('css')`.

---

### 4. A package that ships a CLI script

```text
install/bin/blogimport   ->   bin/blogimport
```

Worth asking first whether you want this at all. If your script is useful
standalone, a composer `bin` entry in your `composer.json` puts it in
`vendor/bin/` with no install step — that is how `installModule` itself is
shipped. Use `install/bin/` for a script the consumer is *meant to edit*: a
starting point rather than a tool.

---

### 5. Everything at once — a full feature package

```text
vendor/acme/blog/install/
├── install.php
├── config/@site.php                          fragment merged into config/site.php
├── config/blog.php                           whole file the package owns
├── database/migrations/20260801000001_create_blog_tables.php
├── database/seeds/AcmeBlogSeeder.php
├── htdocs/css/blog.css
├── htdocs/js/blog.js
├── bin/blogimport
└── support/README.md
```

```sh
vendor/bin/installModule acme/blog --dry-run
```

```text
  merge  config/site.php  (create and merge)
  copy   config/blog.php  (new)
  copy   database/migrations/20260801000001_acme_blog_create_blog_tables.php  (new)
  copy   database/seeds/AcmeBlogSeeder.php  (new)
  copy   htdocs/css/blog.css  (new)
  copy   htdocs/js/blog.js  (new)
  copy   bin/blogimport  (new)
  copy   support/README.md  (new)

8 would be written, 0 skipped, 0 refused
```

Nothing is written until the whole plan exists. A conflict anywhere is reported
alongside everything else instead of surfacing halfway through and leaving the
package half-copied.

---

### 6. Installing from inside your own application

The installer takes a path as readily as a package name, which is useful for a
module in your own repo that ships its own assets:

```sh
vendor/bin/installModule application/welcome
```

That looks for `application/welcome/install/`. You can also point straight at an
install directory:

```sh
vendor/bin/installModule application/welcome/install
```

Both are recorded in the receipt under the path, so re-running still skips.

---

### 7. Upgrading when a package changes a file

You installed `acme/blog`, then edited `htdocs/css/blog.css`. The package ships
a new version of it. Nothing is lost:

```text
  REFUSE  htdocs/css/blog.css  (edited since it was installed (-o to discard those edits))

0 written, 3 skipped, 1 refused
Some files were refused. Re-run with -o to replace them.
```

Exit code is 1, so a scripted install cannot report success while leaving files
uncopied.

The installer can say *"edited since it was installed"* rather than merely
*"exists"* because it recorded a hash of what it wrote. Diff, then decide:

```sh
diff vendor/acme/blog/install/htdocs/css/blog.css htdocs/css/blog.css
vendor/bin/installModule acme/blog -o        # take theirs, discard yours
```

If you had **not** edited it, the same upgrade reads differently — this is the
package moving on, and `-o` is the ordinary way to take it:

```text
  REFUSE  htdocs/css/blog.css  (installed version differs from the one this package now ships (-o to update))
```

---

## Reference

```text
installModule <package|path> [options]

  <package>       composer name, e.g. orange/acl  ->  vendor/orange/acl/install
  <path>          directory under the application root

  -n, --dry-run   report what would happen, write nothing
  -o, --overwrite replace destinations that already exist
      --root=DIR  application root (default: auto-detected)
      --no-color  plain output
  -h, --help      this message
```

Exit `0` when everything was written or skipped, `1` when anything was refused
or the install could not start.

### install.php

Every key is optional; omit the file entirely if you have nothing to declare.

| Key        | Type       | Meaning                                          |
| ---------- | ---------- | ------------------------------------------------ |
| `name`     | `string`   | Composer name of the package.                    |
| `requires` | `string[]` | PHP extensions that must be loaded.              |
| `php`      | `string`   | Minimum PHP version.                             |
| `after`    | `string[]` | Printed after a successful install.              |

Requirements are checked **before** the plan is built, so a machine missing an
extension is told so instead of receiving half a package:

```text
"acme/blog" cannot be installed here:
  PHP extension "pdo_mysql" is not loaded.
```

---

## How it decides: copy, skip or refuse

For every file, in this order:

| Situation                                             | Result                        |
| ----------------------------------------------------- | ----------------------------- |
| Destination does not exist                            | `copy (new)`                  |
| Destination is byte-identical (ignoring whitespace)   | `skip (identical)`            |
| `-o` was given                                        | `copy (overwriting)`          |
| We installed it, untouched, package now ships different | `REFUSE (…now ships…)`      |
| We installed it, and it has since been edited         | `REFUSE (edited since…)`      |
| It exists and we never installed it                   | `REFUSE (…not installed by this package)` |

Comparison ignores whitespace and line endings throughout. A file reformatted on
checkout, or rewritten with CRLF, is not a user edit, and reporting it as a
conflict would give you nothing to act on.

---

## Migrations are renamed

This is the one thing not copied byte-for-byte.

Phinx identifies a migration by the number at the front of its filename, and
requires the class inside to match the rest of that name. Two packages that both
picked `20260801000001` would collide on a number neither author could see the
other choosing — and the loser is not a merge conflict, it is a migration that
silently never runs.

So the package name is folded into both:

```text
acme/blog  ships   20260801000001_create_blog_tables.php
                   final class CreateBlogTables

           lands   20260801000001_acme_blog_create_blog_tables.php
                   final class AcmeBlogCreateBlogTables
```

**The version number is preserved.** It is the migration's identity: changing it
would make an application that has already run the migration run it again.
Never renumber a migration you have shipped.

Two rules the installer enforces, loudly rather than quietly:

```text
  REFUSE  ...  (not a phinx migration filename (expected <version>_<name>.php))
  REFUSE  ...  (class CreateStuff does not match its filename (phinx expects CreateBlogTables))
  REFUSE  ...  (expected exactly one class extending AbstractMigration)
```

The second catches a migration that was already broken on phinx's own terms —
better to say that than to rename it into a differently broken state. The third
refuses to guess when a file has two migration classes or none.

Already-prefixed names are left alone, so re-shipping is not
`acme_blog_acme_blog_...`. And a migration already installed under a different
version number is recognised, not duplicated:

```text
  skip   ...  (already installed as 20260101000009_acme_blog_create_blog_tables.php)
```

---

## Seeds are not renamed

Seeders carry no version and no ordering. Phinx runs them by class name — which
is what a person types:

```sh
composer db:seed -s AcmeBlogSeeder
```

A package prefix would make that harder to type without buying the
collision-safety the version number needs. So seeders are copied as named, and
a clash is reported as an ordinary conflict.

**Name yours for your package** (`AcmeBlogSeeder`, not `BlogSeeder`). They land
in one flat directory shared with the application and every other package.

If your seeder must run after another, phinx has a mechanism for it:

```php
public function getDependencies(): array
{
    return ['OrangeAclSeeder'];
}
```

---

## The receipt

`var/installed-modules.json` records what each package installed and a hash of
what it wrote:

```json
{
    "orange/acl": {
        "installed": "2026-08-05T13:33:19+00:00",
        "files": {
            "database/migrations/20260801000001_orange_acl_create_acl_tables.php": "sha256:c56999…",
            "database/seeds/OrangeAclSeeder.php": "sha256:022c44…"
        }
    }
}
```

It buys two things nothing else can provide.

**It separates your edits from ours.** Without it the installer can only ask
"does the destination exist", which cannot tell a file it wrote last week from
one you have since changed — so it would have to either clobber both or refuse
both. With a hash, unchanged is a silent skip and changed is a conflict that
names itself, and `-o` becomes a decision about *your* edits rather than a
blanket hammer.

**It answers where a file came from.** A phinx migration in
`database/migrations/` looks exactly like one written by hand.

**Commit it** — and check that you can. Applications commonly ignore the whole
of `var/` as runtime scratch, which this is not: it is a record of your
application's composition. In a `.gitignore` with `var/*`, add the exception:

```gitignore
var/*
!var/installed-modules.json
```

Without it, a fresh clone starts with no receipt, and every file a package
already installed reads as *"exists and was not installed by this package"*.
Nothing is damaged — the installer still refuses rather than clobbering — but
you lose the distinction between your edits and a package upgrade, which is
most of what the receipt is for.

It is written sorted, so it diffs cleanly.

---

## Design notes

**Standalone by construction.** `bin/installModule` uses no container, no
console service, and no application bootstrap; it `require`s its four classes
directly. It is registered as a composer `bin`, so it ships to anyone who
installs `orange/framework` — including someone who installed *only*
`orange/framework`, where none of those things exist.

**Plan, then apply.** `ModuleInstaller::plan()` builds every action before
anything is written, and `apply()` just walks them. That is what makes
`--dry-run` worth trusting: it is the same code path, stopped one step earlier.

**Where the code lives.**

| File                                  | Role                                     |
| ------------------------------------- | ---------------------------------------- |
| `bin/installModule`                   | argument parsing, output, exit codes     |
| `src/installer/ModuleInstaller.php`   | planning and applying                    |
| `src/installer/InstallAction.php`     | one intended change to one file          |
| `src/installer/Manifest.php`          | `install.php`                            |
| `src/installer/Receipt.php`           | `var/installed-modules.json`             |
| `unittest/tests/ModuleInstallerTest.php` | 36 tests over all of the above         |

**A worked example** ships in this repo's sibling packages:
`vendor/orange/acl/install/` is a real package install tree — a migration, a
seeder holding only the row the package genuinely cannot run without, and an
`install.php` whose `after` block explains what copying the files did not do.
