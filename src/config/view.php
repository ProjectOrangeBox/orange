<?php

declare(strict_types=1);

/*
 * The view engine renders a template with data - that is all it does.
 *
 * There is nothing here about where views live, because it no longer looks: it
 * is handed a path. Everything that used to be configured here for finding one
 * - view paths, default view paths, aliases, and the search tuning that came
 * with them - now belongs to ViewFinder and its generated view map. See
 * config/viewfinder.php.
 */

return [
    // where compiled string templates (renderString) are written, since those
    // have to become real files before they can be require()'d
    'temp directory' => sys_get_temp_dir(),

    // re-compile a string template on every render instead of reusing the
    // cached file
    'debug' => DEBUG,

    'extension' => '.php',

    // how many characters of a string template's hash become a sub-directory,
    // so the temp directory does not end up holding thousands of flat files
    'sub path size' => 6,
];
