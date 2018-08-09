<?php
/**
 * Cache_export
 * Insert description here
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2018
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version 2.0
 *
 * required
 * core:
 * libraries:
 * models:
 * helpers:
 * functions:
 *
 * @show Cache as file using var_export
 */
class Cache_export extends CI_Driver {
	/**
	 * track if the combined cached configuration has been loaded
	 *
	 * @var boolean
	 */
	protected $config = [];

	/**
	 * __construct
	 * Insert description here
	 *
	 * @param $cache_config
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function __construct(&$config) {
		$this->config = &$config;
	}

	/**
	 * get
	 * Insert description here
	 *
	 * @param $id
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function get($id) {
		$get = FALSE;

		if (is_file($this->config['cache_path'].$id.'.meta.php') && is_file($this->config['cache_path'].$id.'.php')) {
			$meta = $this->get_metadata($id);
			if (time() > $meta['expire']) {
				$this->delete($id);
			} else {
				$get = include $this->config['cache_path'].$id.'.php';
			}
		}

		return $get;
	}

	/**
	 * save
	 * Insert description here
	 *
	 * @param $id
	 * @param $data
	 * @param $ttl
	 * @param $include
	 *
	 * @return
	 *
	 */
	public function save($id, $data, $ttl = null, $include = false) {
		$ttl = ($ttl) ? $ttl : cache_ttl();

		if (is_array($data) || is_object($data)) {
			$data = '<?php return '.str_replace('stdClass::__set_state', '(object)', var_export($data, true)).';';
		}

		atomic_file_put_contents($this->config['cache_path'].$id.'.meta.php', '<?php return '.var_export(['strlen' => strlen($data), 'time' => time(), 'ttl' => (int) $ttl, 'expire' => (time() + $ttl)], true).';');
		$save = atomic_file_put_contents($this->config['cache_path'].$id.'.php', $data);

		if ($include) {
			$save = include $this->config['cache_path'].$id.'.php';
		}

		return $save;
	}

	/**
	 * delete
	 * Insert description here
	 *
	 * @param $id
	 *
	 * @return
	 *
	 */
	public function delete($id) {
		return (isset($this->config['cache_multiple_servers'])) ? $this->multi_delete($id) : $this->single_delete($id);
	}

	/**
	 * increment - unsupported
	 *
	 * @param $id - unsupported
	 * @param $offset - unsupported
	 *
	 * @return boolean
	 *
	 */
	public function increment($id, $offset = 1) {
		return false;
	}

	/**
	 * decrement - unsupported
	 *
	 * @param $id - unsupported
	 * @param $offset - unsupported
	 *
	 * @return boolean
	 *
	 */
	public function decrement($id, $offset = 1) {
		return false;
	}

	/**
	 * clean - unsupported
	 *
	 * @return boolean
	 */
	public function clean() {
		return false;
	}

	/**
	 * cache info
	 *
	 * @param $type - unsupported
	 *
	 * @return array
	 *
	 */
	public function cache_info($type = NULL) {
		return [
			'cache_path'=>$this->config['cache_path'],
			'cache_allowed'=>$this->config['cache_allowed'],
			'encryption_key'=>$this->config['encryption_key'],
			'cache_servers'=>$this->config['cache_servers'],
			'cache_server_secure'=>$this->config['cache_server_secure'],
			'cache_url'=>$this->config['cache_url'],
			'cache_multiple_servers'=>$this->config['cache_multiple_servers'],
		];
	}

	/**
	 * Return detailed information on a specific item in the cache.
	 *
	 * @param $id string - Cache item name
	 *
	 * @return mixed - Metadata for the cached item
	 *
	 */
	public function get_metadata($id) {
		return (!is_file( $this->config['cache_path'].$id.'.meta.php') || !is_file( $this->config['cache_path'].$id.'.php')) ? FALSE : include  $this->config['cache_path'].$id.'.meta.php';
	}

	/**
	 * Is support
	 *
	 * @return boolean
	 *
	 */
	public function is_supported() {
		return TRUE;
	}

	/**
	 * endpoint_delete
	 * Insert description here
	 *
	 * @param $request
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example ci('cache')->export->endpoint_delete($request);
	 */
	public function endpoint_delete($request) {
		if (!in_array(ci()->input->ip_address(), $this->config['cache_allowed'])) {
			exit(13);
		}

		list($hmac, $id) = explode(chr(0), hex2bin($request));

		if (md5( $this->config['encryption_key'].$id) !== $hmac) {
			exit(13);
		}

		$this->single_delete($id);
		echo $request;
		exit(200);
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
	public function cache($key, $closure, $ttl = null) {
		if (!$cache = $this->get($key)) {
			$ci = ci();
			$cache = $closure($ci);
			$this->save($key, $cache, $ttl);
		}

		return $cache;
	}

	/**
	 * single_delete
	 * Insert description here
	 *
	 * @param $id
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	protected function single_delete($id) {
		$php_file = $this->config['cache_path'].$id.'.php';
		$meta_file = $this->config['cache_path'].$id.'.meta.php';

		@unlink($php_file);
		@unlink($meta_file);

		remove_php_file_from_opcache($php_file);
		remove_php_file_from_opcache($meta_file);
	}

	/**
	 * multi_delete
	 * Insert description here
	 *
	 * @param $id
	 *
	 * @return boolean
	 *
	 * @access
	 * @static
	 * @throws
	 * @example ci('cache')->export->multi_delete($key);
	 */
	protected function multi_delete($id) {
		/* get the array of other servers */
		$cache_servers =  $this->config['cache_servers'];

		/* create the hmac key */
		$hmac = bin2hex(md5($this->config['encryption_key'].$id).chr(0).$id);

		/* multiple threaded curl to other web heads */
		$mh = curl_multi_init();

		foreach ($cache_servers as $idx=>$server) {
			$url = 'http'.($this->config['cache_server_secure'] ? 's://' : '://').$server.'/'.trim( $this->config['cache_url'], '/').'/'.$hmac;
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

} /* end class */
