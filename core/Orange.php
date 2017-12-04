<?php
/**
 * Orange Framework Extension
 *
 * @package	CodeIgniter / Orange
 * @author Don Myers
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 *
 * required
 * core:
 * libraries: o, load
 * models:
 * helpers:
 *
 */
define('ORANGE_VERSION', '2.0.0');

/* save these for later before we modify it */
define('ROOTPATHS', get_include_path());

/* register loader */
spl_autoload_register('codeigniter_autoload');

/* our "orange" tools */
require __DIR__ . '/../libraries/O.php';

/* bring in CodeIgniter core */
require_once BASEPATH.'core/CodeIgniter.php';

/* NEW - shorter syntax */
function &ci() {
	return CI_Controller::get_instance();
}

/**
 * Class registry
 *
 * This function acts as a singleton.	 If the requested class does not
 * exist it is instantiated and set to a static variable.	 If it has
 * previously been instantiated the variable is returned.
 * only use for app,orange,base files
 *
 * @author Don Myers
 * @access	public
 * @param string	the class name being requested
 * @param string	the directory where the class should be found
 * @param string	the class name prefix
 * @return	object
 *
 * -- CI OVERRIDDEN --
 * because this will search all of our include paths
 * Not just APPPATH and BASEPATH
 *
 */
function &load_class($class, $directory = 'libraries', $param = NULL) {
	static $_classes = array();

	/* is $_classes empty? if so it's the first time here add the packages to the search path */
	if (count($_classes) == 0) {
		include APPPATH . 'config/autoload.php';

		if (file_exists(APPPATH . 'config/' . ENVIRONMENT . '/autoload.php')) {
			include APPPATH . 'config/' . ENVIRONMENT . '/autoload.php';
		}

		/* add packages - hardcore fast */
		set_include_path(ROOTPATHS . PATH_SEPARATOR . APPPATH . PATH_SEPARATOR . implode('/' . PATH_SEPARATOR, $autoload['packages']) . PATH_SEPARATOR . BASEPATH);
	}

	// Does the class exist? If so, we're done...
	if (isset($_classes[$class])) {
		return $_classes[$class];
	}

	$name = false;

	/* is this a core CI_ class? these are only in the system "basepath" folder */
	if (file_exists(BASEPATH . $directory . '/' . $class . '.php')) {
		$name = 'CI_' . $class;

		if (class_exists($name, false) === false) {
			require BASEPATH . $directory . '/' . $class . '.php';
		}
	}

	/* is this a orange extended class? these are only in the orange package folder */
	if (file_exists(ORANGEPATH . '/' . $directory . '/' . config_item('subclass_prefix') . $class . '.php')) {
		$name = config_item('subclass_prefix') . $class;

		if (class_exists($name, false) === false) {
			require_once ORANGEPATH . '/' . $directory . '/' . $name . '.php';
		}
	}

	// Did we find the class?
	if ($name === false) {
		// Note: We use exit() rather then show_error() in order to avoid a
		// self-referencing loop with the Exceptions class
		set_status_header(503);
		echo 'Unable to locate the specified class: ' . $class . '.php';
		exit(1);
	}

	// Keep track of what we just loaded
	is_loaded($class);

	$_classes[$class] = isset($param) ? new $name($param) : new $name();

	return $_classes[$class];
}

/**
 * codeigniter_autoload function.
 *
 * This naming works because of the naming
 * my changes put on the controller and models
 * (suffixes of Controller or _model)
 *
 * @author Don Myers
 * @access public
 * @param mixed $class
 * @return void
 */
function codeigniter_autoload($class) {
	/* composer autoloader knows where controller base classes are */
	if ($file = stream_resolve_include_path($class . '.php')) { /* is it on any of the include paths? */
		require_once $file;

		return true;
	} elseif (substr($class, -6) == '_model') { /* is it a CI model? */
		if ($file = stream_resolve_include_path('models/' . $class . '.php')) {
			ci()->load->model($class);

			return true;
		}
	} elseif (substr($class, -10) == 'Controller') { /* is it a CI Controller? */
		if ($file = stream_resolve_include_path('controllers/' . $class . '.php')) {
			include $file;

			return true;
		}
	} elseif (substr($class, -6) == '_trait') { /* is it a CI Controller trait? */
		if ($file = stream_resolve_include_path('controllers/traits/' . $class . '.php')) {
			include $file;

			return true;
		}
	} elseif ($file = stream_resolve_include_path('libraries/' . $class . '.php')) {
		ci()->load->library($class);

		return true;
	} elseif (substr($class, -10) == 'Middleware') {	
		if ($file = stream_resolve_include_path('middleware/' . $class . '.php')) {
			include $file;
		
			return true;
		}
	} elseif (substr($class,0,7) == 'Plugin_') {	
		if ($file = stream_resolve_include_path('libraries/pear_plugins/' . $class . '.php')) {
			include $file;
		
			return true;
		}
	} elseif (substr($class,0,9) == 'Validate_') {	
		if ($file = stream_resolve_include_path('libraries/validations/' . $class . '.php')) {
			include $file;
		
			return true;
		}
	} elseif (substr($class,0,7) == 'Filter_') {	
		if ($file = stream_resolve_include_path('libraries/filters/' . $class . '.php')) {
			include $file;
		
			return true;
		}
	}
	
	/* beat's me let the next autoloader give it a shot */
	return false;
}

/**
 * override url_helper
 * https://www.codeigniter.com/user_guide/helpers/url_helper.html#site_url
 * 
 * To provide replacement of "tags" loaded from /config/paths.php
 *
 * @author Don Myers
 * @param  string [$uri = ''] URI string
 * @param  string [$protocol = NULL] Protocol, e.g. ‘http’ or ‘https’
 * @return string
 */
 function site_url($uri = '', $protocol = NULL) {
 	/* call the parent feature first */
	$uri = ci()->config->site_url($uri, $protocol);

	/* load or create the cache file */
	$paths = cache_var_export::cache('get_path', function () {
		$array = [];

		$paths = config('paths');

		foreach ($paths as $m => $t) {
			$array['{' . $m . '}'] = $t;
		}

		return ['keys' => array_keys($array), 'values' => array_values($array)];
	});

	/* replace array with array */
	return str_replace($paths['keys'], $paths['values'], $uri);
}

/**
 * make a global wrapper function to access configuration easier
 *
 * @author Don Myers
 * @param  string $setting = null setting group/file and array key or simply the group/file if you would like the entire setting array
 * @param  mixed $default = null If the group/file & key are not found this is the default value returned. This is helpful if the configuration was never setup or you are using place holders
 * @return mixed the value of the group/file and/or key value pair. Default is returned if no matching group/file + key are found
 */
/* make a global wrapper function to access settings easier */
function config($setting = null, $default = null) {
	return ci()->config->item($setting,$default);
}

/**
 * simple escape for " which can be used in HTML output
 *
 * @author Don Myers
 * @param  string $string String you would like escaped
 * @return string escaped string
 */
function esc($string) {
	return str_replace('"', '\"', $string);
}

/* html escape */
function e($string) {
	return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function getPublicObjectVars($obj) {
  return get_object_vars($obj);
}

/* dump to root */
function d2r() {
	$args = func_get_args();

	foreach ($args as $idx=>$arg) {
		if (!is_scalar($arg)) {
			$args[$idx] = json_encode($arg);
		}
	}

	file_put_contents(ROOTPATH.'/'.__METHOD__.'.log','{'.date('H:i:s').'} '.implode('::',$args).chr(10),FILE_APPEND | LOCK_EX);
}
