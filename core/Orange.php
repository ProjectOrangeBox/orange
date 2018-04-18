<?php

define('ORANGE_VERSION', '2.0.0');

require APPPATH.'config/paths.php';

define('CACHEPATH',ROOTPATH.$config['cache']);
define('LOGPATH',ROOTPATH.$config['logs']);

require ORANGEPATH.'/libraries/Orange_autoload_files.php';

orange_autoload_files::load(CACHEPATH.'/autoload_files.php');

spl_autoload_register('codeigniter_autoload');

assert_options(ASSERT_CALLBACK,function($file, $line, $code, $desc = ''){
	$error = '<!doctype html>
	<title>Assertion Failed</title>
	<style>
	body, html { text-align: center; padding: 150px; background-color: #492727; font: 20px Helvetica, sans-serif; color: #fff; font-size: 18px;}
	h1 { font-size: 150%; }
	article { display: block; text-align: left; width: 650px; margin: 0 auto; }
	</style>
	<article>
	<h1>Assertion Failed</h1>
	<div>
	<p>File: '.$file.'</p>
	<p>Line: '.$line.'</p>
	<p>Code: '.$code.'</p>
	<p>Description: '.$desc.'</p>
	</div>
	</article>';

	echo (defined('STDIN')) ? strip_tags(substr($error,strpos($error,'<article>'),strpos($error,'</article>') - strpos($error,'<article>'))).chr(10) : $error;
	exit(1);
});

require_once BASEPATH.'core/CodeIgniter.php';

/**
 * newer / smarter version of get_instance
 *
 * @param string $class name of the library you are looking for
 *
 * @return $this instance of controller
 *
 * @throws Exception
 * @examples ci()->load->library('email')
 * @examples ci('email')->send()
 * @examples ci('page')->render()
 */
function &ci($class=null) {
	/* this function uses the "bail on first match" */
	if ($class) {
		$class = strtolower($class);

		if ($class == 'load') {
			return CI_Controller::get_instance()->load;
		} elseif (CI_Controller::get_instance()->load->is_loaded($class)) {
			return CI_Controller::get_instance()->$class;
		} else {
			$op = orange_paths();
			if (isset($op['classes'][config_item('subclass_prefix').$class]) || isset($op['classes']['ci_'.$class])) {
				CI_Controller::get_instance()->load->library($class);
				return CI_Controller::get_instance()->$class;
			} elseif (codeigniter_autoload($class)) {
				return CI_Controller::get_instance()->$class;
			} else {
				throw new Exception('ci('.$class.') not found');
			}
		}
	}

	return CI_Controller::get_instance();
}

/**
 * Class registry - Overrides the CodeIgniter Default
 *
 * This function acts as a singleton. If the requested class does not
 * exist it is instantiated and set to a static variable. If it has
 * previously been instantiated the variable is returned.
 *
 * @param	string	the class name being requested
 * @param	string	the directory where the class should be found
 * @param	mixed	an optional argument to pass to the class constructor
 * @return	object
 */
function &load_class($class, $directory = 'libraries', $param = NULL) {
	static $_classes = array();

	if (isset($_classes[$class])) {
		return $_classes[$class];
	}

	$name = false;
	$subclass_prefix = config_item('subclass_prefix');

	if (class_exists('ci_'.$class)) {
		$name = 'CI_'.$class;
	}

	if (class_exists($subclass_prefix.$class)) {
		$name = $subclass_prefix.$class;
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

/**
 * CodeIgniter / Orange registered autoload function 
 *
 * @param $class
 *
 * @return boolean
 *
 */
function codeigniter_autoload($class) {
	/* load the autoload config array */
	$op = orange_paths();
	
	/* normalize the class name */
	$class = strtolower($class);
	
	/* is it in the class array? */
	if (isset($op['classes'][$class])) {
		require $op['classes'][$class];
		return true;
	}

	/* is it in the models array? */
	if (isset($op['models'][$class])) {
		ci()->load->model($class);
		return true;
	}

	/* is it in the libraries array? */
	if (isset($op['libraries'][$class])) {
		ci()->load->library($class);
		return true;
	}

	/* can't find this class file notify the autoload (return false) to let somebody else have a shot */
	return false;
}

/**
 * site_url
 * Insert description here
 *
 * @param $uri
 * @param $protocol
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
function site_url($uri = '', $protocol = NULL) {
	$uri = ci()->config->site_url($uri, $protocol);
	
	$file_path = CACHEPATH.'/site_url.php';

	if (ENVIRONMENT == 'development' || !file_exists($file_path)) {
		$paths = config('paths',[]);

		foreach ($paths as $find => $replace) {
			$site_url['keys'][] = '{'.strtolower($find).'}';
			$site_url['values'][] = $replace;
		}

		atomic_file_put_contents($file_path,'<?php return '.var_export($site_url,true).';');
	}

	$paths = include $file_path;

	return str_replace($paths['keys'], $paths['values'], $uri);
}

/**
 * config
 * Wrapper for configure with dot notation
 * ci('config')->dot_item(...)
 *
 * @param $setting
 * @param $default
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
function config($setting,$default='%%no_value%%') {
	$value = ci('config')->dot_item($setting,$default);

	/* only throw an error if nothing found and no default given */
	if ($value === '%%no_value%%') {
		throw new Exception('The config variable "'.$setting.'" is not set and no default was provided.');
	}

	return $value;
}

/**
 * esc
 * Insert description here
 *
 * @param $string
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
function esc($string) {
	return str_replace('"', '\"', $string);
}

/**
 * e
 * Insert description here
 *
 * @param $string
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
function e($string) {
	return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * env
 * Insert description here
 *
 * @param $key
 * @param $default
 * @param $required
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
function env($key,$default=null) {
	if (!isset($_ENV[$key]) && $default === null) {
		die('The environmental variable "'.$key.'" not set.'.PHP_EOL);
	}

	return (isset($_ENV[$key])) ? $_ENV[$key] : $default;
}

/**
 * l
 * Insert description here
 *
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
function l() {
	$args = func_get_args();

	foreach ($args as $idx=>$arg) {
		if (!is_scalar($arg)) {
			$args[$idx] = json_encode($arg);
		}
	}

	$build = date('Y-m-d H:i:s').chr(10);

	foreach ($args as $a) {
		$build .= chr(9).$a.chr(10);
	}

	file_put_contents(LOGPATH.'/l.log',$build,FILE_APPEND | LOCK_EX);
}

/**
 * unlock_session
 * Insert description here
 *
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
function unlock_session() {
	session_write_close();
}

/**
 * console
 * Insert description here
 *
 * @param $var
 * @param $type
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
function console($var, $type = 'log') {
	echo '<script type="text/javascript">console.'.$type.'('.json_encode($var).')</script>';
}

/**
 * orange_paths
 * Insert description here
 *
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
function orange_paths() {
	static $_ORANGE_PATHS;

	return (func_num_args()) ? $_ORANGE_PATHS = func_get_arg(0) : (array)$_ORANGE_PATHS;
}

/**
 * middleware
 * Insert description here
 *
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
function middleware() {
	static $_ORANGE_MIDDLEWARE;

	return (func_num_args()) ? $_ORANGE_MIDDLEWARE = func_get_args() :  (array)$_ORANGE_MIDDLEWARE;
}

/**
 * view
 * Insert description here
 *
 * @param $_view
 * @param $_data
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
function view($_view,$_data=[]) {
	$_op = orange_paths();
	$_file = ltrim(str_replace('.php','',$_view),'/');

	if (!isset($_op['views'][$_file])) {
		throw new Exception('Could not locate view "'.$_file.'"');
	}

	$_view_file = $_op['views'][$_file];
	extract($_data, EXTR_PREFIX_INVALID, '_');
	ob_start();

	include $_view_file;

	return ob_get_clean();
}

/**
 * atomic_file_put_contents
 * Insert description here
 *
 * @param $filepath
 * @param $content
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
function atomic_file_put_contents($filepath, $content) {
	$dirname = dirname($filepath);
	$tmpfname = tempnam($dirname, 'afpc_');

	if (!is_writable($dirname)) {
		throw new Exception('atomic file put contents folder "'.$dirname.'" not writable');
	}

	if ($tmpfname === false) {
		throw new Exception('atomic file put contents could not create temp file');
	}

	$bytes = file_put_contents($tmpfname, $content);

	if ($bytes === false) {
		throw new Exception('atomic file put contents could not file put contents');
	}

	if (chmod($tmpfname, 0644) === false) {
		throw new Exception('atomic file put contents could not change file mode');
	}

	if (rename($tmpfname, $filepath) === false) {
		throw new Exception('atomic file put contents could not make atomic switch');
	}

	if (function_exists('opcache_invalidate')) {
		opcache_invalidate($filepath, true);
	} elseif (function_exists('apc_delete_file')) {
		apc_delete_file($filepath);
	}

	if (function_exists('log_message')) {
		log_message('debug', 'atomic_file_put_contents wrote '.$filepath.' '.$bytes.' bytes');
	}

	return $bytes;
}

/**
 * remove_php_file_from_opcache
 * Insert description here
 *
 * @param $fullpath
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
function remove_php_file_from_opcache($fullpath) {
	$success = (is_file($fullpath)) ? unlink($fullpath) : true;

	if (function_exists('opcache_invalidate')) {
		opcache_invalidate($filepath, true);
	} elseif (function_exists('apc_delete_file')) {
		apc_delete_file($filepath);
	}

	return $success;
}

/**
 * convert_to_real
 * Insert description here
 *
 * @param $value
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
function convert_to_real($value) {
	/* return on first match multiple exists */

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

/**
 * convert_to_string
 * Insert description here
 *
 * @param $value
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
function convert_to_string($value) {
	/* return on first match multiple exists */

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
 * simplify_array
 * Insert description here
 *
 * @param $array
 * @param $key
 * @param $value
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
function simplify_array($array, $key = 'id', $value = null) {
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

/**
 * cache
 * Insert description here
 *
 * @param $key
 * @param $closure
 * @param $ttl
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
function cache($key, $closure, $ttl = null) {
	if (!$cache = ci('cache')->get($key)) {
		$cache = $closure();
		$ttl = ($ttl) ? (int) $ttl : cache_ttl();
		ci('cache')->save($key, $cache, $ttl);
	}

	return $cache;
}

/**
 * cache_ttl
 * Insert description here
 *
 * @param $use_window
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
function cache_ttl($use_window=true) {
	$cache_ttl = (int)config('cache_ttl',0);
	$window_adder = ($use_window) ? mt_rand(-15,15) : 0;

	return ($cache_ttl == 0) ? 0 : (max(0,$cache_ttl + $window_adder));
}

/**
 * delete_cache_by_tags
 * Insert description here
 *
 * @param $args
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
function delete_cache_by_tags($args) {
	if (is_array($args)) {
		$tags = $args;
	} elseif(strpos($args,'.') !== false) {
		$tags = explode('.', $args);
	} else {
		$tags = func_get_args();
	}

	log_message('debug', 'delete_cache_by_tags '.implode(', ', $tags));

	ci('event')->trigger('delete cache by tags', $tags);

	$cached_keys = ci('cache')->cache_info();

	if (is_array($cached_keys)) {
		foreach ($cached_keys as $key) {
			if (count(array_intersect(explode('.', $key['name']), $tags))) {
				ci()->cache->delete($key['name']);
			}
		}
	}
}

/**
 * filter_filename
 * Insert description here
 *
 * @param $str
 * @param $ext
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
function filter_filename($str,$ext=null) {
	$str = strtolower(trim(preg_replace('#\W+#', '_', $str), '_'));

	return ($ext) ? $str.'.'.$ext : $str;
}

/**
 * filter_human
 * Insert description here
 *
 * @param $str
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
function filter_human($str) {
	return ucwords(str_replace('_',' ',strtolower(trim(preg_replace('#\W+#',' ', $str),' '))));
}

/**
 * filter_human
 * Insert description here
 *
 * @param $str
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
function filter_visible($str) {
	return preg_replace("/[^\\x20-\\x7E]/mi", '', $str);
}
