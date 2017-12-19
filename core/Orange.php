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
		set_include_path(ROOTPATHS . PATH_SEPARATOR . rtrim(APPPATH,'/') . PATH_SEPARATOR . implode(PATH_SEPARATOR, $autoload['packages']) . PATH_SEPARATOR . rtrim(BASEPATH,'/'));
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
		if (substr($class, -17) == '_controller_trait') {
			if ($file = stream_resolve_include_path('controllers/traits/' . $class . '.php')) {
				include $file;
	
				return true;
			}
		}

		if (substr($class, -12) == '_model_trait') {
			if ($file = stream_resolve_include_path('models/traits/' . $class . '.php')) {
				include $file;
	
				return true;
			}
		}

		if (substr($class, -14) == '_library_trait') {
			if ($file = stream_resolve_include_path('library/traits/' . $class . '.php')) {
				include $file;
	
				return true;
			}
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

/* dump to log in var/logs */
function l() {
	$args = func_get_args();

	foreach ($args as $idx=>$arg) {
		if (!is_scalar($arg)) {
			$args[$idx] = json_encode($arg);
		}
	}

	$build  = date('H:i:s').chr(10);
	
	foreach ($args as $a) {
		$build .= chr(9).$a.chr(10);
	}

	file_put_contents(ROOTPATH.'/var/logs/'.__METHOD__.'.log',$build,FILE_APPEND | LOCK_EX);
}

/**
 * Release the Session Lock placed on a Page
 * @author Don Myers
 */
function unlock_session() {
	/*
	Database Sessions:
	Only MySQL and PostgreSQL databases are officially supported, due to lack of advisory locking mechanisms on other platforms.
	
	Using sessions without locks can cause all sorts of problems, especially with heavy usage of AJAX, and we will not support such cases.
	Use session_write_close() after you’ve done processing session data if you’re having performance issues.
	*/
	session_write_close();
}

function delete_all_cookies() {
	/* Return current Unix timestamp */
	$past = time() - 3600;
	
	/* iterate over HTTP Cookies array */
	foreach ($_COOKIE as $key=>$value) {
		/* Send a cookie */
    setcookie($key,$value,$past,config('config.cookie_path','/'));
	}
}

/**
 * dump something to the browser javascript console
 * @author Don Myers
 * @param mixed $var            
 * @param string [$type = 'log'] the javascript console method to call
 */
function console($var, $type = 'log') {
	echo '<script type="text/javascript">console.'.$type.'(' . json_encode($var) . ')</script>';
}

/**
 * Generic View Method which searches the include path for view files.
 * @author Don Myers
 * @param  string $_view view file we are searching for ie. admin/dashboard/index
 * @param  array $_data view variables to extract for the view
 * @return string contents of the view file
 */
function view($_view,$_data) {
	/* clean up view file name */
	$_view = 'views/'.ltrim(str_replace('.php','',$_view),'/') . '.php';
	
	/* Resolve filename against the include path */
	$_view_file = stream_resolve_include_path($_view);

	/* if we are in development mode create the file in the application folder */
	if ($_view_file === false) {
		throw new Exception('Could not locate view "'.$_view.'"');
	}
	
	/* Import variables into the current symbol table from an array */
	extract($_data, EXTR_PREFIX_INVALID, '_');

	/* start output cache */
	ob_start();

	/* load in view (which now has access to the in scope view data */
	include $_view_file;

	/* capture cache and return */
	return ob_get_clean();
}

/**
 * atomic_file_put_contents function.
 *
 * this is a atomic function to write a file while still allowing access to the previous file until the file is completely rewritten
 * this keeps other processes from getting a half written file or 2 process from trying to write the same file
 *
 * @author Don Myers
 * @param  string $filepath name of the cache file
 * @param  string $content  contents of the cache file
 * @return int bytes written or false on fail
 */
function atomic_file_put_contents($filepath, $content) {
	/* Create file with unique file name */
	$tmpfname = tempnam(dirname($filepath), 'afpc_');

	/* did we get one? - fatal error */
	if ($tmpfname === false) {
		/* fatal error */
		throw new Exception('atomic file put contents could not create temp file');
	}

	/* Write a string to a file and return the number of bytes that were written to the file, or FALSE on failure */
	$bytes = file_put_contents($tmpfname, $content);

	/* did it fail to write? - fatal error */
	if ($bytes === false) {
		/* fatal error */
		throw new Exception('atomic file put contents could not file put contents');
	}

	/* Renames a file or directory on unix/linux this is a atomic action - return TRUE on success or FALSE on failure.*/
	if (rename($tmpfname, $filepath) === false) {
		/* fatal error */
		throw new Exception('atomic file put contents could not make atomic switch');
	}

	/* if we are using opcache or apccache we need to remove it from the cache */
	if (function_exists('opcache_invalidate')) {
		/* Invalidates a cached script */
		opcache_invalidate($filepath, true);
	} elseif (function_exists('apc_delete_file')) {
		/* Deletes files from the opcode cache */
		apc_delete_file($filepath);
	}

	/* Write to debug log */
	log_message('debug', 'atomic_file_put_contents wrote ' . $filepath . ' ' . $bytes . ' bytes');

	/* return file_put_contents() return value */
	return $bytes;
}

/**
 * remove a php file from op or apc cache
 * @author Don Myers
 * @param  string $fullpath full file path as seen in op or apc cache
 * @return bool true success
 */
function remove_php_file_from_opcache($fullpath) {
	$success = (is_file($fullpath)) ? unlink($fullpath) : true; /* success because it not there */

	/* if we are using opcache or apccache we need to remove it from the cache */
	if (function_exists('opcache_invalidate')) {
		/* Invalidates a cached script */
		opcache_invalidate($filepath, true);
	} elseif (function_exists('apc_delete_file')) {
		/* Deletes files from the opcode cache */
		apc_delete_file($filepath);
	}

	return $success;
}

/**
 * Convert a value from one value to it's native value
 * @author Don Myers
 * @param  string $value value to convert
 * @return mixed converted value
 */
function convert_to_real($value) {
	/* try the easy ones first */
	switch (trim(strtolower($value))) {
	case 'true':
		return true;
		break;
	case 'false':
		return false;
		break;
	case 'empty':
		return '';
		break;
	case 'null':
		return null;
		break;
	default:
		/* Finds whether a variable is a number or a numeric string */
		if (is_numeric($value)) {
			/* Finds whether the type of a variable is float */
			return (is_float($value)) ? (float) $value : (int) $value;
		}
	}

	/* Takes a JSON encoded string and converts it into a PHP variable. - NULL is returned if the json cannot be decoded */
	$json = @json_decode($value, true);

	return ($json !== null) ? $json : $value;
}

/**
 * Convert a value from it's native to a string
 * @author Don Myers
 * @param  mixed $value native value to convert
 * @return string string version of a value
 */
function convert_to_string($value) {
	if (is_array($value)) {
		return var_export($value, true);
	}

	if ($value === true) {
		return 'true';
	}

	if ($value === false) {
		return 'false';
	}

	if ($value === null) {
		return 'null';
	}

	return (string) $value;
}

/**
 * Convert from complex array of arrays or objects to a simple key/value pair
 * @author Don Myers
 * @param  array $array complex array of arrays or objects
 * @param  string [$key = id] simple array key value
 * @param  string [$value = null] simple array value from each array entry
 * @return array simple array
 */
function simple_array($array, $key = 'id', $value = null) {
	$value = ($value) ? $value : $key;

	$new_array = [];
	
	/* iterate over array */
	foreach ($array as $row) {
		if (is_object($row)) {
			$new_array[$row->$key] = $row->$value;
		} else {
			$new_array[$row[$key]] = $row[$value];
		}
	}

	return $new_array;
}

# +-+-+-+-+-+ +-+-+-+-+-+-+-+-+-+
# |c|a|c|h|e| |f|u|n|c|i|t|o|n|s|
# +-+-+-+-+-+ +-+-+-+-+-+-+-+-+-+

/**
 * cache data using a closure for simplity
 * @author Don Myers
 * @param  string $key cache key with optional . separators for tags
 * @param  closure $closure closure function (nothing passed in)
 * @param  int [$ttl = null] ttl for the cache entry (default is to use config ttl +)
 * @return mixed cached data
 */
function cache($key, $closure, $ttl = null) {
	if (!$cache = ci()->cache->get($key)) {
		/* There's been a miss,so run our data function and store it */
		$cache = $closure();

		$ttl = ($ttl) ? (int) $ttl : cache_ttl();

		ci()->cache->save($key, $cache, $ttl);
	}

	return $cache;
}

/**
 * Get the TTL + 10 - 30 seconds
 * defaults to 1 second in the development environment
 * @author Don Myers
 * @return int numbers of seconds
 */
function cache_ttl() {
	/* add a between 10 to 30 seconds so multiple caches created at once timeout at random times this is helpful on high traffic sites */
	return (ENVIRONMENT == 'development') ? 1 : mt_rand(10, 30) + (int) config('config.cache_ttl', 60);
}

/**
 * delete cache items based on . separators for tags
 * ie. delete_cache_by_tags('user','admin','gui');
 * @author Don Myers
 */
function delete_cache_by_tags($args) {
	if (is_array($args)) {
		$tags = $args;
	} elseif(strpos($args,'.') !== false) {
		$tags = explode('.', $args);
	} else {
		$tags = func_get_args();
	}
	
	log_message('debug', 'delete_cache_by_tags ' . implode(', ', $tags));

	event::trigger('delete cache by tags', $tags);
	
	/* get all the cache keys */
	$cached_keys = ci()->cache->cache_info();

	if (is_array($cached_keys)) {
		foreach ($cached_keys as $key) {
			if (count(array_intersect(explode('.', $key['name']), $tags))) {
				ci()->cache->delete($key['name']);
			}
		}
	}
}
