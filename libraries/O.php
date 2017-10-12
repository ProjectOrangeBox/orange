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
	 * normalize function.
	 *
	 * @access public
	 * @static
	 * @param mixed $name
	 * @param string $replace (default: '.')
	 * @return void
	 */
	public static function normalize($name, $replace = '.') {
		/*
		look for any sequence of non-alphanumeric characters and replaces it with a single '.'.
		using + you also avoid having two consecutive .'s in your output.
		we also lowercase and trim any leading and trailing .'s
		 */
		return trim(preg_replace('/[^a-z0-9]+/', $replace, strtolower($name)), $replace);
	}

	/**
	 * console function.
	 *
	 * @access public
	 * @static
	 * @param mixed $var
	 * @param string $type (default: 'log')
	 * @return void
	 */
	public static function console($var, $type = 'log') {
		echo '<script type="text/javascript">console.' . $type . '(' . json_encode($var) . ')</script>';
	}

	/**
	 * atomic_file_put_contents function.
	 *
	 * this is a atomic function to write a file while still allowing access to the previous file until the file is completely rewritten
	 * this keeps other processes from getting a half written file or 2 process from trying to write the same file
	 *
	 * @access public
	 * @static
	 * @param mixed $filepath
	 * @param mixed $content
	 * @return void
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
	 * remove_php_file function.
	 *
	 * remove a PHP cached file if it exists
	 *
	 * @access public
	 * @static
	 * @param mixed $fullpath
	 * @return void
	 */
	public static function remove_php_file($fullpath) {
		$success = (is_file($fullpath)) ? unlink($fullpath) : true/* success because it not there */;

		/* if we are using opcache or apccache we need to remove it from the cache */
		if (function_exists('opcache_invalidate')) {
			opcache_invalidate($fullpath, true);
		} elseif (function_exists('apc_delete_file')) {
			apc_delete_file($fullpath);
		}

		return $success;
	}

	/**
	 * convert_to_real function.
	 *
	 * convert to real value from string
	 *
	 * @access public
	 * @static
	 * @param mixed $value
	 * @return void
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
	 * convert_to_string function.
	 *
	 * convert values to string and return asap
	 *
	 * @access public
	 * @static
	 * @param mixed $value
	 * @return void
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
	 * simple_array function.
	 *
	 * @access public
	 * @static
	 * @param mixed $array
	 * @param mixed $key (default: null)
	 * @param mixed $value (default: null)
	 * @return void
	 */
	public static function simple_array($array, $key = null, $value = null) {
		$key   = ($key) ? $key : 'id';
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
	 * cache function.
	 *
	 * @access public
	 * @static
	 * @param mixed $key
	 * @param mixed $closure
	 * @param mixed $ttl (default: null)
	 * @return void
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
	 * ttl function.
	 *
	 * @access public
	 * @static
	 * @return void
	 */
	public static function ttl() {
		/* add a between 10 to 30 seconds so multiple caches created at once timeout at random times this is helpful on high traffic sites */
		return (ENVIRONMENT == 'development') ? 1 : mt_rand(10, 30) + (int) config('config.cache_ttl', 60);
	}

	/**
	 * delete_cache_by_tags function.
	 *
	 * delete cache entries based on period separators
	 * if the cache items contain at least 1 it is added to the returned array
	 *
	 * @access public
	 * @static
	 * @return void
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