<?php

/************************
* Orange Global Functions
*************************/

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
if (!function_exists('ci')) {
	function &ci($class=null) {
		/* this function uses the "return on first match" */
		$CI = get_instance();

		/* did they include a class name? */
		if ($class) {
			/* normalize it */
			$class = strtolower($class);

			/* is it load? that kind of special so handle that directly */
			if ($class == 'load') {
				return $CI->load;
			/* is the class loaded? */
			} elseif (isset($CI->$class)) {
				/* yes - then just return that */
				return $CI->$class;
			} else {
				$CI->load->library($class);

				/* now attached or a error was thrown */
				return $CI->$class;
			}
		}

		/* default CodeIgniter get_instance() */
		return $CI;
	}
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
if (!function_exists('load_class')) {
	function &load_class($class) {
		static $_classes = array();

		if (isset($_classes[$class])) {
			return $_classes[$class];
		}

		$name = false;
		$subclass_prefix = config_item('subclass_prefix');
		$ci_prefix = 'ci_';

		if (class_exists($subclass_prefix.$class)) {
			$path = orange_locator::class($subclass_prefix.$class);
			$class_name = basename(strtolower($path),'.php');
			$name = $subclass_prefix.$class;
		} elseif (class_exists($ci_prefix.$class)) {
			$path = orange_locator::class($ci_prefix.$class);
			$class_name = $ci_prefix.basename(strtolower($path),'.php');
			$name = $ci_prefix.$class;
		}

		if ($name === false) {
			set_status_header(503);
			throw new Exception('Unable to locate the specified class: "'.$class.'.php"');
		}

		is_loaded($class);

		$_classes[$class] = new $class_name();

		return $_classes[$class];
	}
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
if (!function_exists('site_url')) {
	function site_url($uri = '', $protocol = NULL) {
		if ($protocol !== false) {
			/* Call CodeIgniter version first */
			$uri = ci()->config->site_url($uri, $protocol);
		}

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
if (!function_exists('config')) {
	function config($setting,$default='%%no_value%%') {
		$value = ci('config')->dot_item($setting,$default);

		/* only throw an error if nothing found and no default given */
		if ($value === '%%no_value%%') {
			throw new Exception('The config variable "'.$setting.'" is not set and no default was provided.');
		}

		return $value;
	}
}

/**
 * Wrapper for filter
 *
*/
if (!function_exists('filter')) {
	function filter($rule,$field) {
		/* add filter_ if it's not there */
		foreach (explode('|',$rule) as $r) {
			$a[] = 'filter_'.str_replace('filter_','',strtolower($r));
		}

		ci('validate')->single(implode('|',$a),$field);

		return $field;
	}
}

/**
 * Wrapper for validate single
 *
*/
if (!function_exists('valid')) {
	function valid($rule,$field) {
		ci('validate')->single($rule,$field);

		return (!ci('errors')->has());
	}
}

/**
 * escape any single quotes with \"
 *
 * @param $string
 *
 * @return string
 *
 */
if (!function_exists('esc')) {
	function esc($string) {
		return str_replace('"', '\"', $string);
	}
}

/**
 * escape html special chracters
 *
 * @param $string
 *
 * @return string
 *
 */
if (!function_exists('e')) {
	function e($string) {
		return html_escape($string);
	}
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
if (!function_exists('env')) {
	function env($key,$default=null) {
		if (!isset($_ENV[$key]) && $default === null) {
			throw new Exception('The environmental variable "'.$key.'" is not set and no default was provided.');
		}

		return (isset($_ENV[$key])) ? $_ENV[$key] : $default;
	}
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
if (!function_exists('l')) {
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
}

/**
 * End the current session and store session data.
 *
 * @return boolean TRUE on success or FALSE on failure.
 *
 */
if (!function_exists('unlock_session')) {
	function unlock_session() {
		session_write_close();
	}
}

/**
 * Show output in Browser Console
 *
 * @param $var mixed - converted to json
 * @param $type - browser console log types defaults to log
 *
 */
if (!function_exists('console')) {
	function console($var, $type = 'log') {
		echo '<script type="text/javascript">console.'.$type.'('.json_encode($var).')</script>';
	}
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
if (!function_exists('view')) {
	function view($_view,$_data=[]) {
		/* clean up the view path */
		$_file = trim(str_replace('.php','',$_view),'/');

		/* get a list of all the found views */
		if (!$_op = orange_locator::view($_file)) {
			/* Not Found */
			throw new Exception('Could not locate view "'.$_file.'"');
		}

		/* import variables into the current symbol table from an only prefix invalid/numeric variable names with _ 	*/
		extract($_data, EXTR_PREFIX_INVALID, '_');

		/* turn on output buffering */
		ob_start();

		/* bring in the view file */
		include $_op;

		/* return the current buffer contents and delete current output buffer */
		return ob_get_clean();
	}
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
if (!function_exists('atomic_file_put_contents')) {
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
}

/**
 * invalidate it if it's a cached script
 *
 * @param $fullpath
 *
 * @return
 *
 */
if (!function_exists('remove_php_file_from_opcache')) {
	function remove_php_file_from_opcache($filepath) {
		/* flush from the cache */
		if (function_exists('opcache_invalidate')) {
			opcache_invalidate($filepath, true);
		} elseif (function_exists('apc_delete_file')) {
			apc_delete_file($filepath);
		}
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
if (!function_exists('convert_to_real')) {
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
if (!function_exists('convert_to_string')) {
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
if (!function_exists('simplify_array')) {
	function simplify_array($array, $key = 'id', $value = null, $sort = null) {
		$value = ($value) ? $value : $key;
		$new_array = [];

		foreach ($array as $row) {
			if (is_object($row)) {
				if ($value == '*') {
					$new_array[$row->$key] = $row;
				} else {
					$new_array[$row->$key] = $row->$value;
				}
			} else {
				if ($value == '*') {
					$new_array[$row[$key]] = $row;
				} else {
					$new_array[$row[$key]] = $row[$value];
				}
			}
		}

		switch($sort) {
			case 'desc':
			case 'd':
				krsort($new_array,SORT_NATURAL | SORT_FLAG_CASE);
			break;
			case 'asc':
			case 'a':
				ksort($new_array,SORT_NATURAL | SORT_FLAG_CASE);
			break;
		}

		return $new_array;
	}
}

/**
 * Reference to the CI_Controller method.
 *
 * Returns current CI instance object
 *
 * @return CI_Controller
 */
if (!function_exists('get_instance')) {
	function &get_instance() {
		return CI_Controller::get_instance();
	}
}

if (!function_exists('_assert_handler')) {
	function _assert_handler($file,$line,$code,$desc='') {
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
	}
}

if (!function_exists('load_config')) {
	function load_config($name,$variable='config') {
		$$variable = false;

		if (file_exists(APPPATH.'config/'.$name.'.php')) {
			require APPPATH.'config/'.$name.'.php';
		}

		if (file_exists(APPPATH.'config/'.ENVIRONMENT.'/'.$name.'.php')) {
			require APPPATH.'config/'.ENVIRONMENT.'/'.$name.'.php';
		}

		return $$variable;
	}
}

if (!function_exists('quick_merge')) {
	function quick_merge($template,$data=[]) {
		if (preg_match_all('/{([^}]+)}/m',$template,$matches)) {
			foreach ($matches[1] as $key) {
				$template = str_replace('{'.$key.'}',$data[$key],$template);
			}
		}

		return $template;
	}
}
