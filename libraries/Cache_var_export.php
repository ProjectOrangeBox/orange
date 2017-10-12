<?php
/**
 * CodeIgniter File Caching Class using var_export which is faster than regular serialized arrays because as .php file arrays they are loaded faster
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

class Cache_var_export {
	protected static $config;

	/**
	 * Fetch from cache
	 *
	 * @param	string	$id Cache ID
	 * @return mixed Data on success,FALSE on failure
	 */
	 public static function init($config) {
			self::$config = $config;
	 }

	/**
	 * get function.
	 * 
	 * @access public
	 * @param mixed $id
	 * @return void
	 */
	public static function get($id) {
		/* if it's not enabled always return false */
		$get = FALSE;

		if (DEBUG != 'development') {
			if (self::$config['cache_default'] !== 'dummy') {
				if (is_file(self::$config['cache_path'] . $id . '.meta.php') && is_file(self::$config['cache_path'] . $id . '.php')) {
					$meta = self::get_metadata($id);

					if ($meta['ttl'] > 0 && time() > $meta['expire']) {
						self::delete($id);
					} else {
						$get = include self::$config['cache_path'] . $id . '.php';
					}
				}
			}
		}

		return $get;
	}

	// ------------------------------------------------------------------------

	/**
	 * Save into cache
	 *
	 * @param	string	$id Cache ID
	 * @param	mixed $data Data to store
	 * @param	int $ttl	 Time to live in seconds
	 * @param	bool $include after we save the php file load it
	 * @return bool	 TRUE on success,FALSE on failure
	 */
	public static function save($id, $data, $ttl = null, $include = FALSE) {
		$ttl = ($ttl) ? $ttl : o::ttl();

		/* convert to php */
		if (is_array($data) || is_object($data)) {
			$data = '<?php return ' . str_replace('stdClass::__set_state', '(object)', var_export($data, true)) . ';';
		}

		/* atomic_file_put_contents handles the op/apc cache flush */

		/* caches meta data */
		self::save_metadata($id, $ttl, strlen($data));

		/* save the cache php file */
		$save = o::atomic_file_put_contents(self::$config['cache_path'] . $id . '.php', $data);

		if ($include) {
			$save = include self::$config['cache_path'] . $id . '.php';
		}

		return $save;
	}

	// ------------------------------------------------------------------------

	/**
	 * Get Cache Metadata
	 *
	 * @param	mixed key to get cache metadata on
	 * @return mixed FALSE on failure,array on success.
	 */
	public static function get_metadata($id) {
		return (!is_file(self::$config['cache_path'] . $id . '.meta.php') || !is_file(self::$config['cache_path'] . $id . '.php')) ? FALSE : include self::$config['cache_path'] . $id . '.meta.php';
	}

	public static function save_metadata($id, $ttl, $strlen) {
		return o::atomic_file_put_contents(self::$config['cache_path'] . $id . '.meta.php', '<?php return ' . var_export(['strlen' => $strlen, 'time' => time(), 'ttl' => (int) $ttl, 'expire' => (time() + $ttl)], true) . ';');
	}

	// ------------------------------------------------------------------------

	/**
	 * Delete from Cache
	 *
	 * @param	mixed unique identifier of item in cache
	 * @return bool	true on success/false on failure
	 */
	public static function delete($id) {
		return (self::$config['cache_multiple_servers']) ? self::multi_delete($id) : self::single_delete($id);
	}

	// ------------------------------------------------------------------------

	/**
	 * Delete from Cache
	 *
	 * @param	mixed unique identifier of item in cache
	 * @return bool	true on success/false on failure
	 */

	protected static function single_delete($id) {
		return (o::remove_php_file(self::$config['cache_path'] . $id . '.php') && o::remove_php_file(self::$config['cache_path'] . $id . '.meta.php'));
	}

	/**
	 * multi_delete function.
	 * 
	 * @access protected
	 * @param mixed $id
	 * @return void
	 */
	protected static function multi_delete($id) {
		/* issue delete to each server */
		$cache_servers = self::$config['cache_servers'];

		$hmac = bin2hex(md5(self::$config['encryption_key'] . $id) . chr(0) . $id);

		/* setup the multi connection */
		$mh = curl_multi_init();

		/* attach the servers */
		foreach ($cache_servers as $idx => $server) {
			$url = 'http' . (self::$config['cache_server_secure'] ? 's://' : '://') . $server . '/' . trim(self::$config['cache_url'], '/') . '/' . $hmac;

			$ch[$idx] = curl_init();

			curl_setopt($ch[$idx], CURLOPT_URL, $url);
			curl_setopt($ch[$idx], CURLOPT_HEADER, 0);
			curl_setopt($ch[$idx], CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch[$idx], CURLOPT_TIMEOUT, 3);
			curl_setopt($ch[$idx], CURLOPT_CONNECTTIMEOUT, 3);

			curl_multi_add_handle($mh, $ch[$idx]);
		}

		$active = null;

		/* execute the handles */
		do {
			$mrc = curl_multi_exec($mh, $active);
		} while ($mrc == CURLM_CALL_MULTI_PERFORM);

		while ($active && $mrc == CURLM_OK) {
			if (curl_multi_select($mh) != -1) {
				do {
					$mrc = curl_multi_exec($mh, $active);
				} while ($mrc == CURLM_CALL_MULTI_PERFORM);
			}
		}

		/* clean up */
		foreach ($cache_servers as $idx => $server) {
			curl_multi_remove_handle($mh, $ch[$idx]);
		}

		/* close the multi connection */
		curl_multi_close($mh);

		return TRUE;
	}

	/**
	 * endpoint_delete function.
	 * 
	 * @access public
	 * @param mixed $request
	 * @return void
	 */
	public static function endpoint_delete($request) {
		/* is it a allowed remote address */
		if (!in_array(ci()->input->ip_address(), self::$config['cache_allowed'])) {
			exit(13); /* die hard */
		}

		list($hmac, $id) = explode(chr(0), hex2bin($request));

		/* verify the incoming request */
		if (md5(self::$config['encryption_key'] . $id) !== $hmac) {
			exit(13); /* die hard */
		}

		self::single_delete($id);

		echo $request;
		exit(200);
	}

	/**
	 * cache function.
	 * 
	 * @access public
	 * @param mixed $key
	 * @param mixed $closure
	 * @param mixed $ttl (default: null)
	 * @return void
	 */
	public static function cache($key, $closure, $ttl = null) {
		if (!$cache = self::get($key)) {
			/* make a reference we can pass into the closure */
			$ci = ci();

			$cache = $closure($ci);

			/* There's been a miss, so run our closure, save the cache php file and load to make it active */
			self::save($key, $cache, $ttl);
		}

		return $cache;
	}

} /* end class */