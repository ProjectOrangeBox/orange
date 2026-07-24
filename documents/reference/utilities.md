# Reference: Utility helpers (`Dot` & `Ary`)

[← Reference index](README.md) · Guide: [Global helpers](../13-helpers.md)

Two static helper classes in `orange\framework\helpers\` for working with data structures. They're
plain utility classes — call the static methods directly; nothing needs the container.

```php
use orange\framework\helpers\Dot;
use orange\framework\helpers\Ary;
```

---

## `Dot` — dot‑notation access

Read, write, and reshape **arrays or `\stdClass`** objects using dot‑notated keys
(`'user.address.city'`). All methods are static and share a configurable delimiter (default `.`).

| Method | Returns | Purpose |
|--------|---------|---------|
| `Dot::get(array\|stdClass $data, string $key, mixed $default = null)` | mixed | Read a nested value by dot key; `$default` if the path is missing. |
| `Dot::set(array\|stdClass &$data, string $key, mixed $value)` | void | Set a nested value by dot key, creating intermediate arrays as needed. Modifies `$data` in place. |
| `Dot::isset(array\|stdClass &$data, string $key)` | bool | Whether the dot key exists. |
| `Dot::unset(array\|stdClass &$data, string $key)` | void | Remove the dot key from `$data`. |
| `Dot::flatten(array\|stdClass $array, string $prepend = '')` | array | Collapse a nested structure into a single‑level array with dot‑notated keys. |
| `Dot::expand(array\|stdClass $array)` | array | Inverse of `flatten()` — turn a dot‑keyed flat array into a nested one. Throws `InvalidValue` on a key conflict. |
| `Dot::changeDelimiter(string $delimiter)` | void | Change the delimiter used by all `Dot` methods (default `.`). |

```php
$data = ['user' => ['name' => 'Ada', 'address' => ['city' => 'London']]];

Dot::get($data, 'user.address.city');        // 'London'
Dot::get($data, 'user.address.zip', 'n/a');  // 'n/a'
Dot::set($data, 'user.address.zip', 'SW1');  // adds the nested key

Dot::flatten($data);
// ['user.name' => 'Ada', 'user.address.city' => 'London', 'user.address.zip' => 'SW1']

Dot::expand(['a.b.c' => 1]);                  // ['a' => ['b' => ['c' => 1]]]
```

> The framework's `config->get('file.key')` uses the same dot idea; `Dot` exposes it for your own
> data.

---

## `Ary` — array helpers

Static helpers for reshaping and safely reading arrays.

| Method | Returns | Purpose |
|--------|---------|---------|
| `Ary::remapKey(array $input, array $map)` | array | Rename keys: for each `old => new` in `$map`, move `$input[old]` to `$input[new]`. |
| `Ary::remapValue(array $input, array $map)` | array | Replace values: any value matching a key in `$map` is swapped for `$map[value]`. |
| `Ary::makeAssociated(array $array, string $key = 'id', string $value = '*', ?string $sort = null, int $flags = -1)` | array | Turn a list of rows (arrays **or** objects) into an associative array keyed by each row's `$key`. `$value` is a field name, or `'*'` for the whole row. Optionally sort. |
| `Ary::element(string $item, array $array, mixed $default = null)` | mixed | Safely read one key — the value, or `$default` if absent. |
| `Ary::elements(string\|array $items, array $array, mixed $default = null)` | array | Read several keys into a new array, filling `$default` for any that are missing. |
| `Ary::randomElement(array $array)` | mixed | A random element of the array. |
| `Ary::wrapArray(array $array, string $prefix = '', string $suffix = '', string $separator = '', string $parentPrefix = '', string $parentSuffix = '')` | string | Wrap each element with `$prefix`/`$suffix`, join with `$separator`, then wrap the whole with `$parentPrefix`/`$parentSuffix`. |

```php
Ary::remapKey(['fname' => 'Ada'], ['fname' => 'first_name']);
// ['first_name' => 'Ada']

$rows = [['id' => 7, 'name' => 'Ada'], ['id' => 9, 'name' => 'Ben']];
Ary::makeAssociated($rows);              // [7 => ['id'=>7,'name'=>'Ada'], 9 => [...]]
Ary::makeAssociated($rows, 'id', 'name'); // [7 => 'Ada', 9 => 'Ben']

Ary::element('missing', ['a' => 1], 'default');   // 'default'
Ary::elements(['a', 'b'], ['a' => 1], 0);          // ['a' => 1, 'b' => 0]

Ary::wrapArray(['a', 'b'], '<li>', '</li>', "\n", '<ul>', '</ul>');
// <ul><li>a</li>\n<li>b</li></ul>
```

`makeAssociated()`'s `$sort` accepts `'asc'`/`'a'` (ksort), `'desc'`/`'d'` (krsort), or any of
`asort`, `arsort`, `ksort`, `krsort`, `natcasesort`, `natsort`, `shuffle`, `sort`, `rsort` to call
that function directly; an unknown value throws.

---

[← Reference index](README.md) · Guide: [Global helpers](../13-helpers.md)
