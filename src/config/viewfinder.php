<?php

declare(strict_types=1);

/*
 * Defaults for the ViewFinder service.
 *
 * All three sections are normally supplied whole by the application - generated
 * in development by scanning PSR-4 roots for views/ directories, and committed
 * as a plain array for production. Empty defaults mean an application with no
 * view config still boots; every lookup simply misses and throws ViewNotFound
 * naming the keys it tried.
 *
 * Keys are expected lower cased. Matching is case insensitive and the generator
 * folds them once at build time, so nothing has to fold the maps per request.
 */

return [
    // namespaced and unique - 'application/welcome/main/index' => absolute path
    'views' => [],

    // un-namespaced, first writer wins - 'main/index' => absolute path.
    // A vendor package's views live here; an application module overrides one
    // by holding the same key.
    'view fallbacks' => [],

    // name => name, applied before either map above is consulted, so an alias
    // resolves exactly as if the target name had been asked for
    'view aliases' => [],
];
