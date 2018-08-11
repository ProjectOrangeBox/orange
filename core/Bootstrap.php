<?php
/* Orange */

/* register the version */
const ORANGE_VERSION = '2.3';

/* load the orange autoloader library - this builds the "super" search array */
require 'Orange_locator.php';

/* instantiate the orange autoloader static class */
orange_locator::load(CACHEPATH.'/autoload_files.php',2);

/* register the Orange Autoloader */
spl_autoload_register('orange_locator::autoload');

/* setup a assertion handler HTML & CLI output supported */
assert_options(ASSERT_CALLBACK,'_assert_handler');

/* CodeIgniter */

/**
* System Initialization File
*
* Loads the base classes and executes the request.
*
* @package		CodeIgniter
* @subpackage	CodeIgniter
* @category	Front-controller
* @author		EllisLab Dev Team
* @link		https://codeigniter.com/user_guide/
*/

/**
* CodeIgniter Version
*
* @var	string
*
*/
const CI_VERSION = '3.1.9';

/*
* ------------------------------------------------------
*  Load the framework constants
* ------------------------------------------------------
*/
load_config('constants');

/*
* ------------------------------------------------------
*  Load the global functions
* ------------------------------------------------------
*/
require_once(BASEPATH.'core/Common.php');

/*
* ------------------------------------------------------
*  Define a custom error handler so we can log PHP errors
* ------------------------------------------------------
*/
set_error_handler('_error_handler');
set_exception_handler('_exception_handler');
register_shutdown_function('_shutdown_handler');

/*
* ------------------------------------------------------
*  Set the subclass_prefix
* ------------------------------------------------------
*
* Normally the "subclass_prefix" is set in the config file.
* The subclass prefix allows CI to know if a core class is
* being extended via a library in the local application
* "libraries" folder. Since CI allows config items to be
* overridden via data set in the main index.php file,
* before proceeding we need to know if a subclass_prefix
* override exists. If so, we will set this value now,
* before any classes are loaded
* Note: Since the config file data is cached it doesn't
* hurt to load it here.
*/
if (!empty($assign_to_config['subclass_prefix'])) {
	get_config(array('subclass_prefix' => $assign_to_config['subclass_prefix']));
}

/*
* ------------------------------------------------------
*  Load Composer autoloader
* ------------------------------------------------------
*/
require_once(config_item('composer_autoload'));

/*
* ------------------------------------------------------
*  Start the timer... tick tock tick tock...
* ------------------------------------------------------
*/
$BM = load_class('Benchmark');
$BM->mark('total_execution_time_start');
$BM->mark('loading_time:_base_classes_start');

/*
* ------------------------------------------------------
*  Instantiate the config class
* ------------------------------------------------------
*
* Note: It is important that Config is loaded first as
* most other classes depend on it either directly or by
* depending on another class that uses it.
*
*/
$CFG = load_class('Config');

/*
* ------------------------------------------------------
* Important charset-related stuff
* ------------------------------------------------------
*
* Configure mbstring and/or iconv if they are enabled
* and set MB_ENABLED and ICONV_ENABLED constants, so
* that we don't repeatedly do extension_loaded() or
* function_exists() calls.
*
* Note: UTF-8 class depends on this. It used to be done
* in it's constructor, but it's _not_ class-specific.
*
*/
$charset = strtoupper(config_item('charset'));

ini_set('default_charset', $charset);

if (extension_loaded('mbstring')) {
	define('MB_ENABLED', TRUE);
	// mbstring.internal_encoding is deprecated starting with PHP 5.6
	// and it's usage triggers E_DEPRECATED messages.
	@ini_set('mbstring.internal_encoding', $charset);
	// This is required for mb_convert_encoding() to strip invalid characters.
	// That's utilized by CI_Utf8, but it's also done for consistency with iconv.
	mb_substitute_character('none');
} else {
	define('MB_ENABLED', FALSE);
}

// There's an ICONV_IMPL constant, but the PHP manual says that using
// iconv's predefined constants is "strongly discouraged".
if (extension_loaded('iconv')) {
	define('ICONV_ENABLED', TRUE);
	// iconv.internal_encoding is deprecated starting with PHP 5.6
	// and it's usage triggers E_DEPRECATED messages.
	@ini_set('iconv.internal_encoding', $charset);
} else {
	define('ICONV_ENABLED', FALSE);
}

ini_set('php.internal_encoding', $charset);

/*
* ------------------------------------------------------
*  Instantiate the UTF-8 class
* ------------------------------------------------------
*/
$UNI =& load_class('Utf8');

/*
* ------------------------------------------------------
*  Instantiate the URI class
* ------------------------------------------------------
*/
$URI =& load_class('URI');

/*
* ------------------------------------------------------
*  Instantiate the routing class and set the routing
* ------------------------------------------------------
*/
$RTR =& load_class('Router');

/*
* ------------------------------------------------------
*  Instantiate the output class
* ------------------------------------------------------
*/
$OUT =& load_class('Output');

/*
* -----------------------------------------------------
* Load the security class for xss and csrf support
* -----------------------------------------------------
*/
$SEC =& load_class('Security');

/*
* ------------------------------------------------------
*  Load the Input class and sanitize globals
* ------------------------------------------------------
*/
$IN =& load_class('Input');

/*
* ------------------------------------------------------
*  Load the Language class
* ------------------------------------------------------
*/
$LANG =& load_class('Lang');

$BM->mark('loading_time:_base_classes_end');

/*
* ------------------------------------------------------
*  Load the app controller and local controller
* ------------------------------------------------------
*
*/
// Load the base controller class
require_once BASEPATH.'core/Controller.php';

/*
* Get the nessesary variables out of the router
* Try to load the Controller
* verify the method
* and return true for 404 file (page) not found error
* or false for everything checks out!
*
* Because we append the 404 route onto the
* regular expression serch array as (.*)
* it's a catch all if everything else fails
*
*/
$directory = $RTR->directory;
$class = $RTR->class;
$method = $RTR->method;
$params = [];

if ($RTR->route($directory,$class,$method,$params)) {
	/* the (.*) route didn't process right so fall back to generic 404 */
	show_404('Could not route.');
}

$URI->rsegments = array(1=>$class,2=>$method);

if ($method !== '_remap') {
	$params = array_slice($URI->rsegments, 2);
}

$BM->mark('controller_execution_time_( '.$class.' / '.$method.' )_start');

/*
* ------------------------------------------------------
*  Instantiate the requested controller
* ------------------------------------------------------
*/
$CI = new $class();

/*
* ------------------------------------------------------
*  Call the requested method
* ------------------------------------------------------
*/
call_user_func_array(array($CI, $method), $params);

$BM->mark('controller_execution_time_( '.$class.' / '.$method.' )_end');

/* tell the output class to display it's content */
$OUT->_display();

