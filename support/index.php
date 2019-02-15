<?php

/**
 * CodeIgniter
 *
 * An open source application development framework for PHP
 *
 * This content is released under the MIT License (MIT)
 *
 * Copyright (c) 2014 - 2017, British Columbia Institute of Technology
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package	CodeIgniter
 * @author	EllisLab Dev Team
 * @copyright	Copyright (c) 2008 - 2014, EllisLab, Inc. (https://ellislab.com/)
 * @copyright	Copyright (c) 2014 - 2017, British Columbia Institute of Technology (http://bcit.ca/)
 * @license http://opensource.org/licenses/MIT MIT License
 * @link	https://codeigniter.com
 * @since	Version 1.0.0
 * @filesource
 */

/*
 *---------------------------------------------------------------
 * APPLICATION ENVIRONMENT
 *---------------------------------------------------------------
 *
 * You can load different configurations depending on your
 * current environment. Setting the environment also influences
 * things like logging and error reporting.
 *
 * On/Off error handling
 * This can be set to anything, but default usage is:
 *
 *     development
 *     testing
 *     production
 *
 * ENVIRONMENT
 *
 * This can be set to anything.
 * see here: http://www.codeigniter.com/user_guide/libraries/config.html#environments
 * NOTE: If you change these, also change the error_reporting() code below
 *
 * LOG_THRESHOLD
 *
 * This is used in the config.php file for the $config['log_threshold'] = value
 * it is used here to make changing it easier.
 *
 * see here: http://www.codeigniter.com/user_guide/general/errors.html?highlight=log#log_message
 *
 * 0 = Disables logging, Error logging TURNED OFF
 * 1 = Error Messages (including PHP errors)
 * 2 = Debug Messages
 * 3 = Informational Messages
 * 4 = All Messages
 *
 */

/* absolute path to projects root level - >> NO << files below this folder */
define('ROOTPATH', realpath(__DIR__.'/../'));

/* absolute path to project orange box folder? */
define('ORANGEPATH', ROOTPATH.'/packages/projectorangebox/orange');

define('CACHEPATH',ROOTPATH.'/var/cache');
define('LOGPATH',ROOTPATH.'/var/logs');

/* Changes PHP's current directory to directory */
chdir(ROOTPATH);

/* .env file */
if (!file_exists('.env')) {
	echo ROOTPATH.'/.env file missing';
	exit(1); // EXIT_ERROR
}

/* bring in the system .env files */
$_ENV = array_merge($_ENV,parse_ini_file('.env',true,INI_SCANNER_TYPED));

if (file_exists('.env.local')) {
	$_ENV = array_merge($_ENV,parse_ini_file('.env.local',true,INI_SCANNER_TYPED));
}

if ($missing = array_diff_key(array_flip(['DEBUG','ENVIRONMENT']),$_ENV)) {
	$in = ($method) ? ' in '.$method : '';
	$s = (count($missing) > 1) ? 's are' : ' is';
	
	echo 'The following required value'.$s.' missing: '.implode(', ',array_flip($missing)).$in.'.';
	exit(1); // EXIT_ERROR
}

/* absolute path to WWW folder */
define('WWW', dirname(__FILE__));

/*
 *---------------------------------------------------------------
 * ERROR REPORTING
 *---------------------------------------------------------------
 *
 * Different environments will require different levels of error reporting.
 * By default development will show errors but testing and live will hide them.
 */
switch ($_ENV['DEBUG']) {
	case 'phpunit':
		/* if phpunit then setup empty argument as empty array so main/index loads */
		$_ENV['ENVIRONMENT'] = 'phpunit';
		$_SERVER['REMOTE_ADDR'] = '0.0.0.0';
		$_SERVER['argv'] = [];

		error_reporting(E_ALL & ~E_NOTICE);
		ini_set('display_errors', 1);
		
		assert_options(ASSERT_ACTIVE, 1);
		assert_options(ASSERT_WARNING, 0);
		assert_options(ASSERT_QUIET_EVAL, 0);
	break;
	case 'development':
		error_reporting(E_ALL & ~E_NOTICE);
		ini_set('display_errors', 1);
		
		assert_options(ASSERT_ACTIVE, 1);
		assert_options(ASSERT_WARNING, 0);
		assert_options(ASSERT_QUIET_EVAL, 0);
	break;
	case 'testing':
		error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
		ini_set('display_errors', 1);

		assert_options(ASSERT_ACTIVE, 1);
		assert_options(ASSERT_WARNING, 0);
		assert_options(ASSERT_QUIET_EVAL, 0);
	break;
	case 'production':
		error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
		ini_set('display_errors', 0);

		assert_options(ASSERT_ACTIVE, 0);
		assert_options(ASSERT_WARNING, 0);
		assert_options(ASSERT_QUIET_EVAL, 1);
	break;
	default:
		header('HTTP/1.1 503 Service Unavailable.', TRUE, 503);
		echo 'The application environment is not set correctly.';
		exit(1); // EXIT_ERROR
}

define('ENVIRONMENT',$_ENV['ENVIRONMENT']);

if (file_exists('.env.'.$_ENV['ENVIRONMENT'])) {
	$_ENV = $_ENV + parse_ini_file('.env.'.$_ENV['ENVIRONMENT'],true,INI_SCANNER_TYPED);
}

/*
 *---------------------------------------------------------------
 * SYSTEM DIRECTORY NAME
 *---------------------------------------------------------------
 *
 * This variable must contain the name of your "system" directory.
 * Set the path if it is not in the same directory as this file.
 */

$system_path = ROOTPATH.'/vendor/codeigniter/framework/system';

/*
 *---------------------------------------------------------------
 * APPLICATION DIRECTORY NAME
 *---------------------------------------------------------------
 *
 * If you want this front controller to use a different "application"
 * directory than the default one you can set its name here. The directory
 * can also be renamed or relocated anywhere on your server. If you do,
 * use an absolute (full) server path.
 * For more info please see the user guide:
 *
 * https://codeigniter.com/user_guide/general/managing_apps.html
 *
 * NO TRAILING SLASH!
 */

$application_folder = ROOTPATH.'/application';

/*
 *---------------------------------------------------------------
 * VIEW DIRECTORY NAME
 *---------------------------------------------------------------
 *
 * If you want to move the view directory out of the application
 * directory, set the path to it here. The directory can be renamed
 * and relocated anywhere on your server. If blank, it will default
 * to the standard location inside your application directory.
 * If you do move this, use an absolute (full) server path.
 *
 * NO TRAILING SLASH!
 */

$view_folder = $application_folder.'/views';

/*
 * -------------------------------------------------------------------
 *  CUSTOM CONFIG VALUES
 * -------------------------------------------------------------------
 *
 * The $assign_to_config array below will be passed dynamically to the
 * config class when initialized. This allows you to set custom config
 * items or override any default config values found in the config.php file.
 * This can be handy as it permits you to share one application between
 * multiple front controller files, with each file containing different
 * config values.
 *
 * Un-comment the $assign_to_config array below to use this feature
 */
	// $assign_to_config['name_of_config_item'] = 'value of config item';



// --------------------------------------------------------------------
// END OF USER CONFIGURABLE SETTINGS.  DO NOT EDIT BELOW THIS LINE
// --------------------------------------------------------------------

/*
 * ---------------------------------------------------------------
 *  Resolve the system path for increased reliability
 * ---------------------------------------------------------------
 */

// Is the system path correct?
if (!$system_path = realpath($system_path)) {
	header('HTTP/1.1 503 Service Unavailable.', TRUE, 503);
	echo 'Your system folder path does not appear to be set correctly. Please open the following file and correct this: '.pathinfo(__FILE__, PATHINFO_BASENAME);
	exit(3); // EXIT_CONFIG
}

/*
 * -------------------------------------------------------------------
 *  Now that we know the path, set the main path constants
 * -------------------------------------------------------------------
 */

// The name of THIS file
define('SELF', pathinfo(__FILE__, PATHINFO_BASENAME));

// Path to the system directory
define('BASEPATH', $system_path.DIRECTORY_SEPARATOR);

// Path to the front controller (this file) directory
define('FCPATH', dirname(__FILE__).DIRECTORY_SEPARATOR);

// Name of the "system" directory
define('SYSDIR', basename(BASEPATH));


if (!$application_folder = realpath($application_folder)) {
	header('HTTP/1.1 503 Service Unavailable.', TRUE, 503);
	echo 'Your application folder path does not appear to be set correctly. Please open the following file and correct this: '.SELF;
	exit(3); // EXIT_CONFIG
}

define('APPPATH', $application_folder.DIRECTORY_SEPARATOR);

if (!$view_folder = realpath($view_folder)) {
	header('HTTP/1.1 503 Service Unavailable.', TRUE, 503);
	echo 'Your view folder path does not appear to be set correctly. Please open the following file and correct this: '.SELF;
	exit(3); // EXIT_CONFIG
}

define('VIEWPATH', $view_folder.DIRECTORY_SEPARATOR);

/* orange methods */
require_once ORANGEPATH.'/core/Orange.php';

/*
 * --------------------------------------------------------------------
 * LOAD THE BOOTSTRAP FILE
 * --------------------------------------------------------------------
 *
 * And away we go...
 */
require_once ORANGEPATH.'/core/Bootstrap.php';
