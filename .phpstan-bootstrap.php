<?php

declare(strict_types=1);

// PHPStan-only stub: these constants are normally define()'d at real runtime
// (Application::bootstrap()) before any config file loads, but PHPStan
// analyses src/config/*.php in isolation without ever running that
// bootstrap - so it needs to see them defined here to know their type.

if (!defined('__ROOT__')) {
    define('__ROOT__', __DIR__);
}

// only referenced by DirectorySearch's optional 'wwwpath' key style - consuming
// applications define this themselves (see the root project's own bootstrap),
// the framework only ever reads it
if (!defined('__WWW__')) {
    define('__WWW__', __DIR__);
}

if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', 'production');
}

if (!defined('DEBUG')) {
    define('DEBUG', true);
}

if (!defined('CHARSET')) {
    define('CHARSET', 'UTF-8');
}

if (!defined('UNDEFINED')) {
    define('UNDEFINED', chr(0));
}

if (!defined('RUN_MODE')) {
    define('RUN_MODE', 'http');
}
