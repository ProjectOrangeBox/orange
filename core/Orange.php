<?php

/************************
 * Orange Global Functions
 *************************/

/**
 * newer / smarter version of CodeIgniter get_instance()
 *
 * @param $class null
 * @param $object null
 *
 * @return mixed
 *
 * #### Example
 * ```
 * ci('email')->send()
 * ci('event',new Event)
 * ```
 */
if (!function_exists('ci')) {
	function &ci($class=null, &$object=null)
	{
		/* this function uses the "return on first match" */
		$CI = get_instance();

		/* did they include a class name? */
		if ($class && $object == null) {
			/* normalize it */
			$class = strtolower($class);

			/* is it load? that kind of special so handle that directly */
			if (isset($CI->$class)) {
				/* yes - then just return that */
				return $CI->$class;
			} else {
				$CI->load->library($class);

				/* now attached or a error was thrown */
				return $CI->$class;
			}
		} elseif ($class && $object) {
			/* assign a object to a name */
			$CI->$class = &$object;
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
 * @param	$class class name to load
 *
 * @throws \Exception
 *
 * @return object
 *
 */
if (!function_exists('load_class')) {
	function &load_class(string $class)
	{
		static $_classes = array();

		if (isset($_classes[$class])) {
			return $_classes[$class];
		}

		$name = false;
		$subclass_prefix = config_item('subclass_prefix');
		$ci_prefix = 'ci_';

		if (class_exists($subclass_prefix.$class)) {
			$path = orange_locator::class($subclass_prefix.$class);
			$class_name = basename(strtolower($path), '.php');
			$name = $subclass_prefix.$class;
		} elseif (class_exists($ci_prefix.$class)) {
			$path = orange_locator::class($ci_prefix.$class);
			$class_name = $ci_prefix.basename(strtolower($path), '.php');
			$name = $ci_prefix.$class;
		}

		if ($name === false) {
			set_status_header(503);
			throw new \Exception('Unable to locate the specified class: "'.$class.'.php"');
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
 * #### Example
 * ```
 * $url = site_url('/{www theme}/assets/css');
 * ```
 */
if (!function_exists('site_url')) {
	function site_url(string $uri = '', bool $protocol = null) : string
	{
		if ($protocol !== false) {
			/* Call CodeIgniter version first */
			$uri = ci()->config->site_url($uri, $protocol);
		}

		/* where is the cache file? */
		$cache_file_path = CACHEPATH.'/site_url.php';

		/* are we in development mode or is the cache file missing */
		if (ENVIRONMENT == 'development' || !file_exists($cache_file_path)) {
			/* yes - then we need to generate it */
			$paths = config('paths', []);

			/* build the array for easier access later */
			foreach ($paths as $find => $replace) {
				$site_url['keys'][] = '{'.strtolower($find).'}';
				$site_url['values'][] = $replace;
			}

			/* save it */
			atomic_file_put_contents($cache_file_path, '<?php return '.var_export($site_url, true).';');
		}

		/* include the cache file */
		$paths = include $cache_file_path;

		/* return the merge str replace */
		return str_replace($paths['keys'], $paths['values'], $uri);
	}
}

/**
 * Wrapper for getting configure with dot notation
 * ci('config')->dot_item(...)
 *
 * @param string $setting
 * @param mixed $default
 *
 * @throws \Exception
 *
 * @return mixed
 *
 * #### Example
 * ```
 * $foo = config('file.key');
 * $foo = config('file.key2','default value');
 * ```
 */
if (!function_exists('config')) {
	function config(string $setting, $default='%%no_value%%')
	{
		$value = ci('config')->dot_item($setting, $default);

		/* only throw an error if nothing found and no default given */
		if ($value === '%%no_value%%') {
			throw new \Exception('The config variable "'.$setting.'" is not set and no default was provided.');
		}

		return $value;
	}
}

/**
 * Wrapper for validation filters
 * This returns the filtered value
 *
 */
if (!function_exists('filter')) {
	function filter(string $rule, $value)
	{
		/* add filter_ if it's not there */
		foreach (explode('|', $rule) as $r) {
			$a[] = 'filter_'.str_replace('filter_', '', strtolower($r));
		}

		ci('validate')->single(implode('|', $a), $value);

		return $value;
	}
}

/**
 * Wrapper for validate single
 * This return whether there validation
 * passes (true)
 * or fails (false)
 *
 */
if (!function_exists('valid')) {
	function valid(string $rule, $field) : bool
	{
		ci('validate')->single($rule, $field);

		return (!ci('errors')->has());
	}
}

/**
 * Escape any single quotes with \"
 *
 * @param string $string
 *
 * @return string
 *
 */
if (!function_exists('esc')) {
	function esc(string $string) : string
	{
		return str_replace('"', '\"', $string);
	}
}

/**
 * Escape html special characters
 *
 * @param $string
 *
 * @return string
 *
 */
if (!function_exists('e')) {
	function e($input) : string
	{
		return (empty($input)) ? '' : html_escape($input);
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
 * @throws \Exception
 *
 * #### Example
 * ```
 * $foo = env('key');
 * $foo = env('key2','default value');
 * ```
 */
if (!function_exists('env')) {
	function env(string $key, $default=null)
	{
		if (!isset($_ENV[$key]) && $default === null) {
			throw new \Exception('The environmental variable "'.$key.'" is not set and no default was provided.');
		}

		return (isset($_ENV[$key])) ? $_ENV[$key] : $default;
	}
}

/**
 * Simple Logging function for debugging purposes
 * the file name is ALWAYS orange_debug.log
 * and saved in the paths config file log path
 *
 * @params ...mixed
 *
 * @return the number of bytes that were written to the file, or FALSE on failure.
 *
 */
if (!function_exists('l')) {
	function l()
	{
		/* get the number of arguments passed */
		$args = func_get_args();

		$log[] = date('Y-m-d H:i:s');

		/* loop over the arguments */
		foreach ($args as $idx=>$arg) {
			/* is it's not scalar then convert it to json */
			$log[] = (!is_scalar($arg)) ? chr(9).json_encode($arg) : chr(9).$arg;
		}

		/* write it to the log file */
		return file_put_contents(LOGPATH.'/orange_debug.log', implode(chr(10), $log).chr(10), FILE_APPEND | LOCK_EX);
	}
}

/**
 * End the current session and store session data.
 * (7.2 returns a boolean but prior it was null)
 * therefore we don't return anything
 *
 * @return void
 *
 */
if (!function_exists('unlock_session')) {
	function unlock_session() : void
	{
		session_write_close();
	}
}

/**
 * Show output in Browser Console
 *
 * @param mixed $var converted to json
 * @param string $type - browser console log types [log]
 *
 */
if (!function_exists('console')) {
	function console($var, string $type = 'log') : void
	{
		echo '<script type="text/javascript">console.'.$type.'('.json_encode($var).')</script>';
	}
}

/**
 * The most Basic MVC View loader
 *
 * @param string $_view view filename
 * @param array $_data list of view variables
 *
 * @throws \Exception
 *
 * @return string
 *
 * @example $html = view('admin/users/show',['name'=>'Johnny Appleseed']);
 *
 */
if (!function_exists('view')) {
	function view(string $_view, array $_data=[]) : string
	{
		/* clean up the view path */
		$_file = trim(str_replace('.php', '', $_view), '/');

		/* get a list of all the found views */
		if (!$_op = orange_locator::view($_file)) {
			/* Not Found */
			throw new \Exception('Could not locate view "'.$_file.'"');
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
 * @param string $filepath path to the file where to write the data
 * @param mixed $content the data to write
 *
 * @return int the number of bytes that were written to the file.
 */
if (!function_exists('atomic_file_put_contents')) {
	function atomic_file_put_contents(string $filepath, $content) : int
	{
		/* get the path where you want to save this file so we can put our file in the same file */
		$dirname = dirname($filepath);

		/* is the directory writeable */
		if (!is_writable($dirname)) {
			throw new \Exception('atomic file put contents folder "'.$dirname.'" not writable');
		}

		/* create file with unique file name with prefix */
		$tmpfname = tempnam($dirname, 'afpc_');

		/* did we get a temporary filename */
		if ($tmpfname === false) {
			throw new \Exception('atomic file put contents could not create temp file');
		}

		/* write to the temporary file */
		$bytes = file_put_contents($tmpfname, $content);

		/* did we write anything? */
		if ($bytes === false) {
			throw new \Exception('atomic file put contents could not file put contents');
		}

		/* changes file permissions so I can read/write and everyone else read */
		if (chmod($tmpfname, 0644) === false) {
			throw new \Exception('atomic file put contents could not change file mode');
		}

		/* move it into place - this is the atomic function */
		if (rename($tmpfname, $filepath) === false) {
			throw new \Exception('atomic file put contents could not make atomic switch');
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
	function remove_php_file_from_opcache(string $filepath) : bool
	{
		$success = true;

		/* flush from the cache */
		if (function_exists('opcache_invalidate')) {
			$success = opcache_invalidate($filepath, true);
		} elseif (function_exists('apc_delete_file')) {
			$success = apc_delete_file($filepath);
		}

		return $success;
	}
}

/**
 * Try to convert a value to it's real type
 * this is nice for pulling string from a database
 * such as configuration values stored in string format
 *
 * @param string $value
 *
 * @return mixed
 *
 */
if (!function_exists('convert_to_real')) {
	function convert_to_real(string $value)
	{
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
 * @param mixed $value
 *
 * @return string
 *
 */
if (!function_exists('convert_to_string')) {
	function convert_to_string($value) : string
	{
		/* return on first match multiple exists */

		if (is_array($value)) {
			return str_replace('stdClass::__set_state', '(object)', var_export($value, true));
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
 * This will collapse a array with multiple values into a single key=>value pair
 *
 * @param array $array
 * @param string $key id
 * @param string $value null
 * @param string $sort null
 *
 * @return array
 *
 */
if (!function_exists('simplify_array')) {
	function simplify_array(array $array, string $key = 'id', string $value = null, string $sort = null) : array
	{
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

		switch ($sort) {
			case 'desc':
			case 'd':
				krsort($new_array, SORT_NATURAL | SORT_FLAG_CASE);
			break;
			case 'asc':
			case 'a':
				ksort($new_array, SORT_NATURAL | SORT_FLAG_CASE);
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
	function &get_instance()
	{
		return CI_Controller::get_instance();
	}
}

/**
 *
 * Orange Assertion Handler
 *
 * @param $file
 * @param $line
 * @param $code
 * @param $desc
 *
 * @return void
 *
 */
if (!function_exists('_assert_handler')) {
	function _assert_handler($file, $line, $code, $desc='') : void
	{
		/* CLI */
		if (defined('STDIN')) {
			echo json_encode(['file'=>$file,'line'=>$line,'description'=>$desc], JSON_PRETTY_PRINT);

		/* AJAX */
		} elseif (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
			echo json_encode(['file'=>$file,'line'=>$line,'description'=>$desc], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT);

		/* HTML */
		} else {
			echo '<!doctype html><title>Assertion Failed</title>';
			echo '<style>body, html { text-align: center; padding: 150px; background-color: #492727; font: 20px Helvetica, sans-serif; color: #fff; font-size: 18px;}h1 { font-size: 150%; }article { display: block; text-align: left; width: 650px; margin: 0 auto; }</style>';
			echo '<article><h1>Assertion Failed</h1>	<div><p>File: '.$file.'</p><p>Line: '.$line.'</p><p>Code: '.$code.'</p><p>Description: '.$desc.'</p></div></article>';
		}

		exit(1);
	}
}

/**
 *
 * Low Level configuration file loader
 * this does NOT include any database configurations
 *
 * @param string $name filename
 * @param string $variable array variable name there configuration is stored in [config]
 *
 * @return array
 *
 */
if (!function_exists('load_config')) {
	function load_config(string $name, string $variable='config') : array
	{
		$$variable = [];

		if (file_exists(APPPATH.'config/'.$name.'.php')) {
			require APPPATH.'config/'.$name.'.php';
		}

		if (file_exists(APPPATH.'config/'.ENVIRONMENT.'/'.$name.'.php')) {
			require APPPATH.'config/'.ENVIRONMENT.'/'.$name.'.php';
		}

		return $$variable;
	}
}

/**
 *
 * Simple view merger
 * replace {tags} with data in the passed data array
 *
 * @access
 *
 * @param string $template
 * @param array $data []
 *
 * @return string
 *
 * #### Example
 * ```
 * $html = quick_merge('Hello {name}',['name'=>'Johnny'])
 * ```
 */
if (!function_exists('quick_merge')) {
	function quick_merge(string $template, array $data=[]) : string
	{
		if (preg_match_all('/{([^}]+)}/m', $template, $matches)) {
			foreach ($matches[1] as $key) {
				$template = str_replace('{'.$key.'}', $data[$key], $template);
			}
		}

		return $template;
	}
}

/**
 *
 * Get the registered packages
 *
 * @access global
 *
 * @param string $pre false
 * @param string $post false
 * @param bool $sort false
 *
 * @return array
 *
 * #### Example
 * ```php
 *
 * ```
 */
if (!function_exists('get_packages')) {
	function get_packages(string $pre = null, string $post = null, bool $sort = false)
	{
		$autoload = load_config('autoload', 'autoload');

		$packages = $autoload['packages'];

		/* de dup values */
		$packages = array_unique($packages);

		if ($pre) {
			foreach (explode(',', strtolower($pre)) as $x) {
				switch ($x) {
					case 'root':
						array_unshift($packages, '');
					break;
					case 'app':
						array_unshift($packages, rtrim(APPPATH, '/'));
					break;
					case 'system':
						array_unshift($packages, rtrim(BASEPATH, '/'));
					break;
				}
			}
		}

		if ($post) {
			foreach (explode(',', strtolower($post)) as $x) {
				switch ($x) {
					case 'root':
						$packages[] = '';
					break;
					case 'app':
						$packages[] = rtrim(APPPATH, '/');
					break;
					case 'system':
						$packages[] = rtrim(BASEPATH, '/');
					break;
				}
			}
		}

		/* do we need to sort this? */
		if ($sort) {
			sort($packages);
		}

		return $packages;
	}
}
