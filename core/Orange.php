<?php

/* register the version */
define('ORANGE_VERSION','2.0.0');

/* Where is the paths configuration file? */
$paths_config_file = APPPATH.'config/paths.php';

/* is the paths configuration file even there? */
if (!file_exists($paths_config_file)) {
	/* no show error */
	die('Paths Config File Not found at "'.$paths_config_file.'"'.chr(10));
}

/* load the paths file */
include $paths_config_file;

/* get out the 2 paths we need now and set them as constants these are pretty "necessary" paths used thought the system */
define('CACHEPATH',ROOTPATH.$config['cache']);
define('LOGPATH',ROOTPATH.$config['logs']);

/* load the orange autoloader library */
include ORANGEPATH.'/libraries/Orange_autoload_files.php';

/* instancte the orange autoloader class and save the returned array */
$_ORANGE_PATHS = (new Orange_autoload_files(CACHEPATH.'/autoload_files.php',2))->read_cache();

/* register the Orange Autoloader */
spl_autoload_register('codeigniter_autoload');

/* setup a assertion handler HTML & CLI output supported */
assert_options(ASSERT_CALLBACK,function($file,$line,$code,$desc='') {
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

/* Ok now Orange is setup so bring in the CodeIgniter "Bootstrapper" */
require_once BASEPATH.'core/CodeIgniter.php';

/******************
* Global Functions
*******************/

/**
 * newer / smarter version of CodeIgniter get_instance()
 *
 * @param string $class name of the library or model you are looking for
 *
 * @return mixed
 *
 * @throws Exception
 * @examples ci()->load->library('email')
 * @examples ci('email')->send()
 * @examples ci('page')->render()
 */
function &ci($class=null) {
	/* this function uses the "return on first match" */

	/* did they include a class name? */
	if ($class) {
		/* normalize it */
		$class = strtolower($class);

		/* is it load? that kind of special so handle that directly */
		if ($class == 'load') {
			return CI_Controller::get_instance()->load;

		/* is the class loaded? */
		} elseif (CI_Controller::get_instance()->load->is_loaded($class)) {
			/* yes - then just return that */
			return CI_Controller::get_instance()->$class;
		} else {
			/* ok let's see if it's a class we know about */

			/* get the autoloader array */
			$orange_paths = orange_paths('classes');

			/* is it a CI_ class or MY_ class? */
			if (isset($orange_paths[config_item('subclass_prefix').$class]) || isset($orange_paths['ci_'.$class])) {
				/* yes - then load it */
				CI_Controller::get_instance()->load->library($class);

				/* and return it */
				return CI_Controller::get_instance()->$class;

			/* did the codeigniter autoloader load it? */
			} elseif (codeigniter_autoload($class)) {

				/* yes - then return it */
				return CI_Controller::get_instance()->$class;
			} else {
				/* not sure what they are looking for */
				throw new Exception('ci('.$class.') not found');
			}
		}
	}

	/* default CodeIgniter get_instance() */
	return CI_Controller::get_instance();
}

/**
 * Class registry - Overrides the CodeIgniter Default
 *
 * This function acts as a singleton. If the requested class does not
 * exist it is instantiated and set to a static variable. If it has
 * previously been instantiated the variable is returned.
 *
 * @param	string the class name being requested
 * @param	string the directory where the class should be found
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
	$orange_paths = orange_paths();

	/* normalize the class name */
	$class = strtolower($class);

	/* is it in the class array? */
	if (isset($orange_paths['classes'][$class])) {
		require $orange_paths['classes'][$class];
		return true;
	}

	/* is it in the models array? */
	if (isset($orange_paths['models'][$class])) {
		ci()->load->model($class);
		return true;
	}

	/* is it in the libraries array? */
	if (isset($orange_paths['libraries'][$class])) {
		ci()->load->library($class);
		return true;
	}

	/* can't find this class file notify the autoload (return false) to let somebody else have a shot */
	return false;
}

/**
 * site_url
 * Returns your site URL, as specified in your config file.
 * also provides auto merging of "merge" fields in {} format
 *
 * @param $uri
 * @param $protocol
 *
 * @return
 *
 * @example site_url('/{www theme}/assets/css');
 */
function site_url($uri = '', $protocol = NULL) {
	/* Call CodeIgniter version first */
	$uri = ci()->config->site_url($uri, $protocol);

	/* now let's merge if needed */

	/* where is the cache file? */
	$cache_file_path = CACHEPATH.'/site_url.php';

	/* are we in development mode or is the cache file missing */
	if (ENVIRONMENT == 'development' || !file_exists($cache_file_path)) {
		/* yes - then we need to generate it */
		$paths = config('paths',[]);

		/* build the array for easier access later */
		foreach ($paths as $find => $replace) {
			$site_url['keys'][] = '{'.strtolower($find).'}';
			$site_url['values'][] = $replace;
		}

		/* save it */
		atomic_file_put_contents($cache_file_path,'<?php return '.var_export($site_url,true).';');
	}

	/* include the cache file */
	$paths = include $cache_file_path;

	/* return the merge str replace */
	return str_replace($paths['keys'], $paths['values'], $uri);
}

/**
 * Wrapper for configure with dot notation
 * ci('config')->dot_item(...)
 *
 * @param $setting
 * @param $default
 *
 * @return
 *
 * @example $foo = config('file.key');
 * @example $foo = config('file.key2','default value');
 *
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
 * escape any single quotes with \"
 *
 * @param $string
 *
 * @return string
 *
 */
function esc($string) {
	return str_replace('"', '\"', $string);
}

/**
 * escape html special chracters
 *
 * @param $string
 *
 * @return string
 *
 */
function e($string) {
	return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * get a environmental variable with support for default
 *
 * @param $key string environmental variable you want to load
 * @param $default mixed the default value if environmental variable isn't set
 *
 * @return string
 *
 * @example $foo = env('key');
 * @example $foo = env('key2','default value');
 *
 */
function env($key,$default=null) {
	if (!isset($_ENV[$key]) && $default === null) {
		throw new Exception('The environmental variable "'.$key.'" is not set and no default was provided.');
	}

	return (isset($_ENV[$key])) ? $_ENV[$key] : $default;
}

/**
 * Simple Logging function for debugging purposes
 * the file name is ALWAYS orange_debug.log
 * and saved in the paths config file log path
 *
 * @params 1 or more mixed parameters
 *
 * @return the number of bytes that were written to the file, or FALSE on failure.
 *
 */
function l() {
	/* get the number of arguments passed */
	$args = func_get_args();

	$log[] = date('Y-m-d H:i:s');

	/* loop over the arguments */
	foreach ($args as $idx=>$arg) {
		/* is it's not scalar then convert it to json */
		$log[] = (!is_scalar($arg)) ? chr(9).json_encode($arg) : chr(9).$arg;
	}

	/* write it to the log file */
	return file_put_contents(LOGPATH.'/orange_debug.log',implode(chr(10),$log).chr(10),FILE_APPEND | LOCK_EX);
}

/**
 * End the current session and store session data.
 *
 * @return boolean TRUE on success or FALSE on failure.
 *
 */
function unlock_session() {
	session_write_close();
}

/**
 * Show output in Browser Console
 *
 * @param $var mixed - converted to json
 * @param $type - browser console log types defaults to log
 *
 */
function console($var, $type = 'log') {
	echo '<script type="text/javascript">console.'.$type.'('.json_encode($var).')</script>';
}

/**
 * Get the current Orange Paths Array
 *
 * @param $var string specific section you are looking for (optional)
 *
 * @return array
 *
 */
function orange_paths($section=null) {
	global $_ORANGE_PATHS;

	return ($section) ? (array)$_ORANGE_PATHS[$section] : (array)$_ORANGE_PATHS;
}

/**
 * Getter / Setter for the current Middleware
 * Note: using it as a setter will overwrite what is currently stored
 *
 * @return array
 *
 * @example middleware('AdminMiddleware','PublicMiddleware','GuiMiddleware','TooltipMiddleware');
 *
 */
function middleware() {
	global $_ORANGE_MIDDLEWARE;

	/* setting or getting? */
	return (func_num_args()) ? $_ORANGE_MIDDLEWARE = func_get_args() :  (array)$_ORANGE_MIDDLEWARE;
}

/**
 * The most Basic MVC View loader
 *
 * @param $_view string view filename
 * @param $_data array list of view variables
 *
 * @return string
 *
 * @example $html = view('admin/users/show',['name'=>'Johnny Appleseed']);
 *
 */
function view($_view,$_data=[]) {
	/* get a list of all the found views */
	$_op = orange_paths('views');

	/* clean up the view path */
	$_file = ltrim(str_replace('.php','',$_view),'/');

	/* is the view file found? */
	if (!isset($_op[$_file])) {
		/* nope! */
		throw new Exception('Could not locate view "'.$_file.'"');
	}

	/* get the view file */
	$_view_file = $_op[$_file];

	/* import variables into the current symbol table from an only prefix invalid/numeric variable names with _ 	*/
	extract($_data, EXTR_PREFIX_INVALID, '_');

	/* turn on output buffering */
	ob_start();

	/* bring in the view file */
	include $_view_file;

	/* return the current buffer contents and delete current output buffer */
	return ob_get_clean();
}

/**
 * Write a string to a file with atomic uninterruptible
 *
 * @param $filepath string path to the file where to write the data
 * @param $content string the data to write
 *
 * @return the number of bytes that were written to the file, or FALSE on failure.
 *
 */
function atomic_file_put_contents($filepath, $content) {
	/* get the path where you want to save this file so we can put our file in the same file */
	$dirname = dirname($filepath);

	/* is the directory writeable */
	if (!is_writable($dirname)) {
		throw new Exception('atomic file put contents folder "'.$dirname.'" not writable');
	}

	/* create file with unique file name with prefix */
	$tmpfname = tempnam($dirname, 'afpc_');

	/* did we get a temporary filename */
	if ($tmpfname === false) {
		throw new Exception('atomic file put contents could not create temp file');
	}

	/* write to the temporary file */
	$bytes = file_put_contents($tmpfname, $content);

	/* did we write anything? */
	if ($bytes === false) {
		throw new Exception('atomic file put contents could not file put contents');
	}

	/* changes file permissions so I can read/write and everyone else read */
	if (chmod($tmpfname, 0644) === false) {
		throw new Exception('atomic file put contents could not change file mode');
	}

	/* move it into place - this is the atomic function */
	if (rename($tmpfname, $filepath) === false) {
		throw new Exception('atomic file put contents could not make atomic switch');
	}

	/* if it's cached we need to flush it out so the old one isn't loaded */
	remove_php_file_from_opcache($filepath);

	/* if log message function is loaded at this point log a debug entry */
	if (function_exists('log_message')) {
		log_message('debug', 'atomic_file_put_contents wrote '.$filepath.' '.$bytes.' bytes');
	}

	/* return the number of bytes written */
	return $bytes;
}

/**
 * invalidate it if it's a cached script
 *
 * @param $fullpath
 *
 * @return
 *
 */
function remove_php_file_from_opcache($fullpath) {
	/* flush from the cache */
	if (function_exists('opcache_invalidate')) {
		opcache_invalidate($filepath, true);
	} elseif (function_exists('apc_delete_file')) {
		apc_delete_file($filepath);
	}
}

/**
 * Try to convert a value to it's real type
 * this is nice for pulling string from a database
 * such as configuration values stored in string format
 *
 * @param $value
 *
 * @return mixed
 *
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
			return (is_float($value)) ? (float)$value : (int)$value;
		}
	}

	$json = @json_decode($value, true);

	return ($json !== null) ? $json : $value;
}

/**
 * Try to convert a value back into a string
 * this is nice for storing string into a database
 * such as configuration values stored in string format
 *
 * @param $value mixed
 *
 * @return string
 *
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
 * this will collapse a array with multiple values into a single key=>value pair
 *
 * @param $array
 * @param $key string value to use for the key
 * @param $value value to use for the value
 *
 * @return array
 *
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
 * Wrapper function to sue the currently loaded cache library in a closure fashion
 *
 * @param $key string cache key
 * @param $closure function to run IF the cached data is not found or has expired
 * @param $ttl integer time to live if empty it will use the default
 *
 * @return mixed cached data
 *
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
 * Get the current Cache Time to Live with optional "window" support to negate a cache stamped
 *
 * @param $use_window boolean
 *
 * @return integer
 *
 */
function cache_ttl($use_window=true) {
	/* get the cache ttl from the config file */
	$cache_ttl = (int)config_item('cache_ttl');

	/* are they using the window option? */
	if ($use_window) {
		/* let determine the window size based on there cache time to live length no more than 5 minutes */
		$window = min(300,ceil($cache_ttl * .02));
		/* add it to the cache_ttl to get our "new" cache time to live */
		$cache_ttl += mt_rand(-$window,$window);
	}

	return $cache_ttl;
}

/**
 * Delete cache records based on dot notation "tags"
 *
 * @param $args mixed rray, period separated list of tags or multiple arguments
 *
 * @return
 *
 * @example delete_cache_by_tags('acl','user','roles');
 * @example delete_cache_by_tags('acl.user.roles');
 * @example delete_cache_by_tags(['acl','user','roles']);
 */
function delete_cache_by_tags($args) {
	/* determine if it's a array, period separated list of tags or multiple arguments */
	if (is_array($args)) {
		$tags = $args;
	} elseif(strpos($args,'.') !== false) {
		$tags = explode('.', $args);
	} else {
		$tags = func_get_args();
	}

	/* log a debug entry */
	log_message('debug', 'delete_cache_by_tags '.implode(', ', $tags));

	/* trigger a event incase somebody else needs to know send in our array of tags by reference */
	ci('event')->trigger('delete-cache-by-tags',$tags);

	/* get all of the currently loaded cache driver cache keys */
	$cached_keys = ci('cache')->cache_info();

	/* if the cache key has 1 or more matching tag delete the entry */
	if (is_array($cached_keys)) {
		foreach ($cached_keys as $key) {
			if (count(array_intersect(explode('.', $key['name']), $tags))) {
				ci()->cache->delete($key['name']);
			}
		}
	}
}

function filter($rule,$field) {
	/* add filter_ if it's not there */
	foreach (explode('|',$rule) as $r) {
		$a[] = 'filter_'.str_replace('filter_','',strtolower($r));
	}

	ci('validate')->single(implode('|',$a),$field);

	return $field;
}

function valid($rule,$field) {
	ci('validate')->single($rule,$field);

	return !ci('errors')->has();
}
