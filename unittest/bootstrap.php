<?php

error_reporting(E_ALL);

define('__ROOT__', realpath(__DIR__ . '/../../../'));
define('__WWW__', realpath(__ROOT__ . '/htdocs'));

define('DEBUG', true);
define('ENVIRONMENT', 'testing');
define('UNDEFINED', chr(0));
define('UNITTEST', true);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(-1);

define('__TESTDIR__', realpath(__DIR__));

$_ENV = array_replace_recursive($_ENV, parse_ini_file(__TESTDIR__ . '/support/env', true, INI_SCANNER_TYPED));

// for testing
define('WORKINGDIR', realpath(__TESTDIR__ . '/working'));
define('MOCKDIR', realpath(__TESTDIR__ . '/mocks'));
define('STUBDIR', realpath(__TESTDIR__ . '/stubs'));
define('ORANGEDIR', realpath(__DIR__ . '/../src'));

require ORANGEDIR . '/helpers/helpers.php';
require ORANGEDIR . '/helpers/errors.php';
require ORANGEDIR . '/helpers/wrappers.php';
