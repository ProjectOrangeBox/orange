<?php
/*
 * Orange Framework Extension
 *
 * @package	CodeIgniter / Orange
 * @author Don Myers
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 *
 * required
 * core:
 * libraries:
 * models:
 * helpers:
 * functions:
 *
 */

/* orange version */
define('ORANGE_VERSION', '2.0.0');

/* save the current system include paths */
define('ROOTPATHS', get_include_path());

/* register our loader */
spl_autoload_register('codeigniter_autoload');

/* and away we go! -> bring in standard CodeIgniter library */
require_once BASEPATH.'core/CodeIgniter.php';

/* additional functions */

/*
wrapper for get_instance with autoload added

Both are the same thing:
$model = get_instance()->example_model;
$model = ci()->example_model;

get_instance()
https://www.codeigniter.com/user_guide/general/ancillary_classes.html?highlight=get_instance#get_instance

Added Autoloader Functionality:
$model = ci('example_model');

*/
function &ci($class=null) {
	/* Added Functionality */
	if ($class) {
		if ($class == 'load') {
			return ci()->load;
		} elseif (ci()->load->is_loaded($class)) {
			return CI_Controller::get_instance()->$class;
		} else {
			if (codeigniter_autoload($class)) {
				return CI_Controller::get_instance()->$class;
			} else {
				throw new Exception('ci('.$class.') not found');
			}
		}
	}

	/* wrapper part */
	return CI_Controller::get_instance();
}

/**
 * Class registry
 *
 * This function acts as a singleton. If the requested class does not
 * exist it is instantiated and set to a static variable. If it has
 * previously been instantiated the variable is returned.
 *
 * @param	string	the class name being requested
 * @param	string	the directory where the class should be found
 * @param	mixed	an optional argument to pass to the class constructor
 * @return	object
 *
 * Added so load_class would search the ORANGEPATH for core classes
 */
function &load_class($class, $directory = 'libraries', $param = NULL) {
	static $_classes = array();

	if (count($_classes) == 0) {
		include APPPATH.'config/autoload.php';

		if (file_exists(APPPATH.'config/'.ENVIRONMENT.'/autoload.php')) {
			include APPPATH.'config/'.ENVIRONMENT.'/autoload.php';
		}

		set_include_path(ROOTPATHS.PATH_SEPARATOR.rtrim(APPPATH,'/').PATH_SEPARATOR.implode(PATH_SEPARATOR, $autoload['packages']).PATH_SEPARATOR.rtrim(BASEPATH,'/'));
	}

	if (isset($_classes[$class])) {
		return $_classes[$class];
	}

	$name = false;

	if (file_exists(BASEPATH.$directory.'/'.$class.'.php')) {
		$name = 'CI_'.$class;

		if (class_exists($name, false) === false) {
			require BASEPATH.$directory.'/'.$class.'.php';
		}
	}

	if (file_exists(ORANGEPATH.'/'.$directory.'/'.config_item('subclass_prefix').$class.'.php')) {
		$name = config_item('subclass_prefix').$class;

		if (class_exists($name, false) === false) {
			require_once ORANGEPATH.'/'.$directory.'/'.$name.'.php';
		}
	}

	if ($name === false) {
		set_status_header(503);
		echo 'Unable to locate the specified class: '.$class.'.php';
		exit(1);
	}

	is_loaded($class);

	$_classes[$class] = isset($param) ? new $name($param) : new $name();

	return $_classes[$class];
}

/*
Orange Autoloader

Autoloader for:
	libraries
	models
	controllers
	controller traits
	model traits
	library traits
	middleware
	libraries
	validations
	filters

*/
function codeigniter_autoload($class) {
	$uclass = ucfirst($class);

	if ($file = stream_resolve_include_path($class.'.php')) {
		require_once $file;

		return true;
	} elseif (substr($class, -6) == '_model') {
		if (stream_resolve_include_path('models/'.$uclass.'.php')) {
			ci()->load->model($class);

			return true;
		}
	} elseif (substr($class, -10) == 'Controller') {
		if ($file = stream_resolve_include_path('controllers/'.$uclass.'.php')) {
			include $file;

			return true;
		}
	} elseif (substr($class, -6) == '_trait') {
		if (substr($class, -17) == '_controller_trait') {
			if ($file = stream_resolve_include_path('controllers/traits/'.$class.'.php')) {
				include $file;

				return true;
			}
		}
		if (substr($class, -12) == '_model_trait') {
			if ($file = stream_resolve_include_path('models/traits/'.$class.'.php')) {
				include $file;

				return true;
			}
		}
		if (substr($class, -14) == '_library_trait') {
			if ($file = stream_resolve_include_path('library/traits/'.$class.'.php')) {
				include $file;

				return true;
			}
		}
	} elseif (stream_resolve_include_path('libraries/'.$uclass.'.php')) {
		ci()->load->library($class);

		return true;
	} elseif (substr($class, -10) == 'Middleware') {
		if ($file = stream_resolve_include_path('middleware/'.$uclass.'.php')) {
			include $file;

			return true;
		}
	} elseif (substr($uclass,0,9) == 'Validate_') {
		if ($file = stream_resolve_include_path('libraries/validations/'.$uclass.'.php')) {
			include $file;

			return true;
		}
	} elseif (substr($uclass,0,7) == 'Filter_') {
		if ($file = stream_resolve_include_path('libraries/filters/'.$uclass.'.php')) {
			include $file;

			return true;
		}
	}

	return false;
}

/**
 * Site URL
 *
 * Returns base_url . index_page [. uri_string]
 *
 * @uses	CI_Config::_uri_string()
 *
 * @param	string|string[]	$uri	URI string or an array of segments
 * @param	string	$protocol
 * @return	string
 *
 * Added so we can "merge" values from the paths config file
 *
 * site_url('{www image}/personal');
 *
 */
function site_url($uri = '', $protocol = NULL) {
	/* run the CodeIgniter version first */
	$uri = ci()->config->site_url($uri, $protocol);

	/* build the paths cache for easy replacement */
	$paths = cache_var_export::cache('get_path', function(){
		$array = [];
		$paths = config('paths');

		foreach ($paths as $m => $t) {
			$array['{'.strtolower($m).'}'] = $t;
		}

		return ['keys' => array_keys($array), 'values' => array_values($array)];
	});

	/* simple find and replace array to array */
	return str_replace($paths['keys'], $paths['values'], $uri);
}

/* wrapper function to grab configuration */
function config($setting = null, $default = null) {
	return ci()->config->item($setting,$default);
}

/* provide simple quote escaping */
function esc($string) {
	return str_replace('"', '\"', $string);
}

/* provide simple html character escaping */
function e($string) {
	return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/* Gets the public properties of the given object */
function get_public_object_vars($obj) {
  return get_object_vars($obj);
}

/* Simple logging function */
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

/* wrapper to unlock a session for ajax calls when the session variables are no longer needed */
function unlock_session() {
	session_write_close();
}

/* Delete all cookies */
function delete_all_cookies() {
	$past = time() - 3600;

	foreach ($_COOKIE as $key=>$value) {
    setcookie($key,$value,$past,config('config.cookie_path','/'));
	}
}

/* wrapper to provide a simple browser logging */
function console($var, $type = 'log') {
	echo '<script type="text/javascript">console.'.$type.'('.json_encode($var).')</script>';
}

/* the most simple view building function */
function view($_view,$_data=[]) {
	$_view = 'views/'.ltrim(str_replace('.php','',$_view),'/').'.php';

	/* use the include paths to search for the view */
	$_view_file = stream_resolve_include_path($_view);

	if ($_view_file === false) {
		throw new Exception('Could not locate view "'.$_view.'"');
	}

	/* extract our data variables */
	extract($_data, EXTR_PREFIX_INVALID, '_');

	ob_start();

	include $_view_file;

	return ob_get_clean();
}

/*
Write a string to a file in a single "atomic" action
using the atomic method doesn't cause problems with other processes trying to read/write to the file while you are writing it */
function atomic_file_put_contents($filepath, $content) {
	$tmpfname = tempnam(dirname($filepath), 'afpc_');

	if ($tmpfname === false) {
		throw new Exception('atomic file put contents could not create temp file');
	}

	$bytes = file_put_contents($tmpfname, $content);

	if ($bytes === false) {
		throw new Exception('atomic file put contents could not file put contents');
	}

	if (rename($tmpfname, $filepath) === false) {
		throw new Exception('atomic file put contents could not make atomic switch');
	}

	if (function_exists('opcache_invalidate')) {
		opcache_invalidate($filepath, true);
	} elseif (function_exists('apc_delete_file')) {
		apc_delete_file($filepath);
	}

	log_message('debug', 'atomic_file_put_contents wrote '.$filepath.' '.$bytes.' bytes');

	return $bytes;
}

/* remove a file from the in memory file cache if you have opcache or apccache installed */
function remove_php_file_from_opcache($fullpath) {
	$success = (is_file($fullpath)) ? unlink($fullpath) : true;

	if (function_exists('opcache_invalidate')) {
		opcache_invalidate($filepath, true);
	} elseif (function_exists('apc_delete_file')) {
		apc_delete_file($filepath);
	}

	return $success;
}

/* convert a string to a real value */
function convert_to_real($value) {
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
		if (is_numeric($value)) {
			return (is_float($value)) ? (float) $value : (int) $value;
		}
	}

	$json = @json_decode($value, true);

	return ($json !== null) ? $json : $value;
}

/* convert a real value back to something human */
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

/* convert a complex associated array into a simple name/value associated pair */
function simple_array($array, $key = 'id', $value = null) {
	$value = ($value) ? $value : $key;

	$new_array = [];

	foreach ($array as $row) {
		if (is_object($row)) {
			$new_array[$row->$key] = $row->$value;
		} else {
			$new_array[$row[$key]] = $row[$value];
		}
	}

	return $new_array;
}

/* cache function using CodeIgniters cache and a closure */
function cache($key, $closure, $ttl = null) {
	if (!$cache = ci('cache')->get($key)) {
		$cache = $closure();
		$ttl = ($ttl) ? (int) $ttl : cache_ttl();
		ci('cache')->save($key, $cache, $ttl);
	}

	return $cache;
}

/* get the caching TTL with a window so they don't all expire at the exact same time on a high volume site */
function cache_ttl($use_window=true) {
	$adjust = ($use_window) ? mt_rand(-15,15) : 0;

	return (ENVIRONMENT == 'development') ? 1 : $adjust + (int) config('config.cache_ttl', 60);
}

/* delete cache items using tags (dot notation filename) */
function delete_cache_by_tags($args) {
	/* split the tags up into an array */
	if (is_array($args)) {
		$tags = $args;
	} elseif(strpos($args,'.') !== false) {
		$tags = explode('.', $args);
	} else {
		$tags = func_get_args();
	}

	log_message('debug', 'delete_cache_by_tags '.implode(', ', $tags));

	event::trigger('delete cache by tags', $tags);

	$cached_keys = ci('cache')->cache_info();

	if (is_array($cached_keys)) {
		foreach ($cached_keys as $key) {
			if (count(array_intersect(explode('.', $key['name']), $tags))) {
				ci()->cache->delete($key['name']);
			}
		}
	}
}

/* global filter function for filenames */
function filter_filename($str,$ext=null) {
	$str = strtolower(trim(preg_replace('#\W+#', '_', $str), '_'));

	return ($ext) ? $str.'.'.$ext : $str;
}

/* global filter function for human name */
function filter_human($str) {
	return ucwords(str_replace('_',' ',strtolower(trim(preg_replace('#\W+#',' ', $str),' '))));
}

/* setter & getter for middleware to be/has been called */
function middleware() {
	global $_middleware;

	return (func_num_args()) ? $_middleware = func_get_args() :  (array)$_middleware;
}
