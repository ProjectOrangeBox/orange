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
 * libraries:
 * models:
 * helpers:
 * functions:
 *
 * Static Library
 */

class O {

	/**
	 * Release the Session Lock placed on a Page
	 * @author Don Myers
	 */
	public static function unlock_session() {
		/*
		Database Sessions:
		Only MySQL and PostgreSQL databases are officially supported, due to lack of advisory locking mechanisms on other platforms.
		
		Using sessions without locks can cause all sorts of problems, especially with heavy usage of AJAX, and we will not support such cases.
		Use session_write_close() after you’ve done processing session data if you’re having performance issues.
		*/
		session_write_close();
	}

	/**
	 * dump something to the browser javascript console
	 * @author Don Myers
	 * @param mixed $var            
	 * @param string [$type = 'log'] the javascript console method to call
	 */
	public static function console($var, $type = 'log') {
		echo '<script type="text/javascript">console.' . $type . '(' . json_encode($var) . ')</script>';
	}

	/**
	 * Generic View Method which searches the include path for view files.
	 * @author Don Myers
	 * @param  string $_view view file we are searching for ie. admin/dashboard/index
	 * @param  array $_data view variables to extract for the view
	 * @return string contents of the view file
	 */
	public static function view($_view,$_data) {
		$_view = 'views/'.ltrim(str_replace('.php','',$_view),'/') . '.php';
	
		$_view_file = stream_resolve_include_path($_view);

		/* if we are in development mode create the file in the application folder */
		if ($_view_file === false) {
			if (DEBUG == 'development') {
				/* then create it */
				@mkdir(APPPATH . dirname($_view), 0777, true);

				file_put_contents(APPPATH . $_view, '<?php' . PHP_EOL . PHP_EOL . ' echo "Error View File: ".__FILE__;' . PHP_EOL);

				die('Error View File ../' . $_view . ' Not Found - because you are in development mode it has been automatically created for you in your application folder.');
			} else {
				errors::show('Could not locate view "'.$_view.'"');
			}
		}
	
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
	public static function atomic_file_put_contents($filepath, $content) {
		/* get a temporary file */
		$tmpfname = tempnam(dirname($filepath), 'afpc_');

		/* did we get one? - fatal error */
		if ($tmpfname === false) {
			/* fatal error */
			throw new Exception('atomic file put contents could not create temp file');
		}

		/* write the contents of our file to the temporary file and grab the bytes written */
		$bytes = file_put_contents($tmpfname, $content);

		/* did it fail to write? - fatal error */
		if ($bytes === false) {
			/* fatal error */
			throw new Exception('atomic file put contents could not file put contents');
		}

		/* rename the temp file to the real file on unix/linux this is a atomic action */
		if (rename($tmpfname, $filepath) === false) {
			/* fatal error */
			throw new Exception('atomic file put contents could not make atomic switch');
		}

		/* if we are using opcache or apccache we need to remove it from the cache */
		if (function_exists('opcache_invalidate')) {
			opcache_invalidate($filepath, true);
		} elseif (function_exists('apc_delete_file')) {
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
	public static function remove_php_file($fullpath) {
		$success = (is_file($fullpath)) ? unlink($fullpath) : true; /* success because it not there */

		/* if we are using opcache or apccache we need to remove it from the cache */
		if (function_exists('opcache_invalidate')) {
			opcache_invalidate($fullpath, true);
		} elseif (function_exists('apc_delete_file')) {
			apc_delete_file($fullpath);
		}

		return $success;
	}

	/**
	 * Convert a value from one value to it's native value
	 * @author Don Myers
	 * @param  string $value value to convert
	 * @return mixed converted value
	 */
	public static function convert_to_real($value) {
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
			if (is_numeric($value)) {
				return (is_float($value)) ? (float) $value : (int) $value;
			}
		}

		/* is it JSON? if not this will return null */
		$json = @json_decode($value, true);

		/* if it's not then I guess it's a regular string */
		return ($json !== null) ? $json : $value;
	}

	/**
	 * Convert a value from it's native to a string
	 * @author Don Myers
	 * @param  mixed $value native value to convert
	 * @return string string version of a value
	 */
	public static function convert_to_string($value) {
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
	public static function simple_array($array, $key = 'id', $value = null) {
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
	public static function cache($key, $closure, $ttl = null) {
		$ci = &get_instance();

		if (!$cache = $ci->cache->get($key)) {
			/* There's been a miss,so run our data function and store it */
			$cache = $closure();

			$ttl = ($ttl) ? (int) $ttl : o::ttl();

			$ci->cache->save($key, $cache, $ttl);
		}

		return $cache;
	}

	/**
	 * Get the TTL + 10 - 30 seconds
	 * defaults to 1 second in the development environment
	 * @author Don Myers
	 * @return int numbers of seconds
	 */
	public static function ttl() {
		/* add a between 10 to 30 seconds so multiple caches created at once timeout at random times this is helpful on high traffic sites */
		return (ENVIRONMENT == 'development') ? 1 : mt_rand(10, 30) + (int) config('config.cache_ttl', 60);
	}

	/**
	 * delete cache items based on . separators for tags
	 * ie. o::delete_cache_by_tags('user','admin','gui');
	 * @author Don Myers
	 */
	public static function delete_cache_by_tags() {
		$tags = func_get_args();

		log_message('debug', 'delete_cache_by_tags ' . implode(', ', $tags));

		event::trigger('delete cache by tags', $tags);

		$cached_keys = ci()->cache->cache_info();

		if (is_array($cached_keys)) {
			foreach ($cached_keys as $key) {
				if (array_intersect(explode('.', $key['name']), $tags)) {
					ci()->cache->delete($key['name']);
				}
			}
		}
	}
		
} /*end class */