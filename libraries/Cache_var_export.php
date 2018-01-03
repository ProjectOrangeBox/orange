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

class Cache_var_export {
	protected static $config;

	public static function init($config) {
		self::$config = $config;
	}

	public static function get($id) {
		$get = FALSE;

		if (DEBUG != 'development') {
			if (self::$config['cache_default'] !== 'dummy') {
				if (is_file(self::$config['cache_path'].$id.'.meta.php') && is_file(self::$config['cache_path'].$id.'.php')) {
					$meta = self::get_metadata($id);
					if ($meta['ttl'] > 0 && time() > $meta['expire']) {
						self::delete($id);
					} else {
						$get = include self::$config['cache_path'].$id.'.php';
					}
				}
			}
		}

		return $get;
	}

	public static function save($id, $data, $ttl = null, $include = FALSE) {
		$ttl = ($ttl) ? $ttl : cache_ttl();

		if (is_array($data) || is_object($data)) {
			$data = '<?php return '.str_replace('stdClass::__set_state', '(object)', var_export($data, true)).';';
		}

		self::save_metadata($id, $ttl, strlen($data));

		$save = atomic_file_put_contents(self::$config['cache_path'].$id.'.php', $data);

		if ($include) {
			$save = include self::$config['cache_path'].$id.'.php';
		}

		return $save;
	}

	public static function get_metadata($id) {
		return (!is_file(self::$config['cache_path'].$id.'.meta.php') || !is_file(self::$config['cache_path'].$id.'.php')) ? FALSE : include self::$config['cache_path'].$id.'.meta.php';
	}

	public static function save_metadata($id, $ttl, $strlen) {
		return atomic_file_put_contents(self::$config['cache_path'].$id.'.meta.php', '<?php return '.var_export(['strlen' => $strlen, 'time' => time(), 'ttl' => (int) $ttl, 'expire' => (time() + $ttl)], true).';');
	}

	public static function delete($id) {
		return (self::$config['cache_multiple_servers']) ? self::multi_delete($id) : self::single_delete($id);
	}

	public static function endpoint_delete($request) {
		if (!in_array(ci()->input->ip_address(), self::$config['cache_allowed'])) {
			exit(13);
		}

		list($hmac, $id) = explode(chr(0), hex2bin($request));

		if (md5(self::$config['encryption_key'].$id) !== $hmac) {
			exit(13);
		}

		self::single_delete($id);
		echo $request;

		exit(200);
	}

	public static function cache($key, $closure, $ttl = null) {
		if (!$cache = self::get($key)) {
			$ci = ci();
			$cache = $closure($ci);

			self::save($key, $cache, $ttl);
		}

		return $cache;
	}

	protected static function single_delete($id) {
		return (remove_php_file_from_opcache(self::$config['cache_path'].$id.'.php') && remove_php_file_from_opcache(self::$config['cache_path'].$id.'.meta.php'));
	}

	protected static function multi_delete($id) {
		$cache_servers = self::$config['cache_servers'];
		$hmac = bin2hex(md5(self::$config['encryption_key'].$id).chr(0).$id);
		$mh = curl_multi_init();

		foreach ($cache_servers as $idx => $server) {
			$url = 'http'.(self::$config['cache_server_secure'] ? 's://' : '://').$server.'/'.trim(self::$config['cache_url'], '/').'/'.$hmac;
			$ch[$idx] = curl_init();
			curl_setopt($ch[$idx], CURLOPT_URL, $url);
			curl_setopt($ch[$idx], CURLOPT_HEADER, 0);
			curl_setopt($ch[$idx], CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch[$idx], CURLOPT_TIMEOUT, 3);
			curl_setopt($ch[$idx], CURLOPT_CONNECTTIMEOUT, 3);
			curl_multi_add_handle($mh, $ch[$idx]);
		}

		$active = null;

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

		foreach ($cache_servers as $idx => $server) {
			curl_multi_remove_handle($mh, $ch[$idx]);
		}

		curl_multi_close($mh);

		return true;
	}

} /* end file */
