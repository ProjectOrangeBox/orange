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
	protected $config;

	public function init(&$config) {
		$this->config = &$config;

		log_message('info', 'Cache_var_export Class Initialized');
	}

	public function get($id) {
		$get = FALSE;

		if (is_file( $this->config['cache_path'].$id.'.meta.php') && is_file( $this->config['cache_path'].$id.'.php')) {
			$meta =  $this->get_metadata($id);

			if (time() > $meta['expire']) {
				 $this->delete($id);
			} else {
				$get = include $this->config['cache_path'].$id.'.php';
			}

		}

		return $get;
	}

	public function save($id, $data, $ttl = null, $include = FALSE) {
		$ttl = ($ttl) ? $ttl : cache_ttl();

		if (is_array($data) || is_object($data)) {
			$data = '<?php return '.str_replace('stdClass::__set_state', '(object)', var_export($data, true)).';';
		}

		 $this->save_metadata($id, $ttl, strlen($data));

		$save = atomic_file_put_contents($this->config['cache_path'].$id.'.php', $data);

		if ($include) {
			$save = include  $this->config['cache_path'].$id.'.php';
		}

		return $save;
	}

	public function get_metadata($id) {
		return (!is_file( $this->config['cache_path'].$id.'.meta.php') || !is_file( $this->config['cache_path'].$id.'.php')) ? FALSE : include  $this->config['cache_path'].$id.'.meta.php';
	}

	public function save_metadata($id, $ttl, $strlen) {
		return atomic_file_put_contents($this->config['cache_path'].$id.'.meta.php', '<?php return '.var_export(['strlen' => $strlen, 'time' => time(), 'ttl' => (int) $ttl, 'expire' => (time() + $ttl)], true).';');
	}

	public function delete($id) {
		return ( $this->config['cache_multiple_servers']) ?  $this->multi_delete($id) :  $this->single_delete($id);
	}

	public function endpoint_delete($request) {
		if (!in_array(ci()->input->ip_address(),  $this->config['cache_allowed'])) {
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

	public function cache($key, $closure, $ttl = null) {
		if (!$cache = $this->get($key)) {
			$ci = ci();

			$cache = $closure($ci);

			$this->save($key, $cache, $ttl);
		}

		return $cache;
	}

	protected function single_delete($id) {
		return (remove_php_file_from_opcache( $this->config['cache_path'].$id.'.php') && remove_php_file_from_opcache( $this->config['cache_path'].$id.'.meta.php'));
	}

	protected function multi_delete($id) {
		$cache_servers =  $this->config['cache_servers'];
		$hmac = bin2hex(md5( $this->config['encryption_key'].$id).chr(0).$id);
		$mh = curl_multi_init();

		foreach ($cache_servers as $idx => $server) {
			$url = 'http'.( $this->config['cache_server_secure'] ? 's://' : '://').$server.'/'.trim( $this->config['cache_url'], '/').'/'.$hmac;
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
