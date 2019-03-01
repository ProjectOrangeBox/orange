<?php
/**
 * Orange
 *
 * An open source extensions for CodeIgniter 3.x
 *
 * This content is released under the MIT License (MIT)
 * Copyright (c) 2014 - 2019, Project Orange Box
 */

/**
 * Cache_export
 *
 * File based cached which stores in a very extremely fast loadable way.
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2019
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version v2.0
 * @filesource
 *
 * @uses input Input
 * @uses # remove_php_file_from_opcache(...)
 *
 * @config cache_path `ROOTPATH.'/var/cache/'`
 * @config cache_default `dummy`
 * @config cache_backup `dummy`
 * @config cache_ttl `60`
 * @config key_prefix `cache.`
 * @config cache_allowed `['192.168.2.123','192.168.2.124']` array of except-able IPs
 * @config encryption_key `30193e8de97f49de586d740f93403dea`
 * @config cache_servers `['192.168.2.123','192.168.2.124']` array of other servers
 * @config cache_server_secure `true`
 * @config cache_url `http://www.example.com/api/cache/`
 *
 */
class Cache_export extends CI_Driver
{
	/**
	 * Configuration array
	 *
	 * @var array
	 */
	protected $config = [];

	/**
	 * Parent Cache Class
	 *
	 * @var \Cache
	 */
	protected $parent;

	/**
	 * CodeIgniter Input
	 *
	 * @var \Input
	 */
	protected $input;

	/**
	 * suffix all export cache file have
	 *
	 * @var array
	 */
	protected $suffix = '.export.php';

	/**
	 *
	 * Constructor
	 *
	 * @param array &$config
	 * @param Cache &$parent
	 *
	 */
	public function __construct(array &$config, Cache &$parent)
	{
		$this->config = &$config;
		$this->parent = &$parent;

		$this->input = &ci('input');
	}

	/**
	 *
	 * fetch an item from the cache store.
	 * If the item does not exist, the method will return FALSE.
	 *
	 * @access public
	 *
	 * @param string $id
	 *
	 * @return mixed
	 *
	 */
	public function get(string $id)
	{
		$get = false;

		if (file_exists($this->config['cache_path'].$id.'.meta'.$this->suffix) && file_exists($this->config['cache_path'].$id.$this->suffix)) {
			$meta = $this->get_metadata($id);
			if (time() > $meta['expire']) {
				$this->delete($id);
			} else {
				$get = include $this->config['cache_path'].$id.$this->suffix;
			}
		}

		return $get;
	}

	/**
	 *
	 * Save an item to the cache store.
	 * If saving fails, the method will return FALSE.
	 * If include is true then return it
	 *
	 * @access public
	 *
	 * @param string $id
	 * @param $data
	 * @param int $ttl null
	 * @param bool $include false
	 *
	 * @throw \Exception
	 * @return mixed
	 *
	 */
	public function save(string $id, $data, int $ttl = null, bool $include = false)
	{
		$ttl = ($ttl) ? $ttl : $this->parent->ttl();

		if (is_array($data) || is_object($data)) {
			$data = '<?php return '.str_replace('stdClass::__set_state', '(object)', var_export($data, true)).';';
		} elseif (is_scalar($data)) {
			$data = '<?php return "'.str_replace('"', '\"', $data).'";';
		} else {
			throw new \Exception('Cache export save unknown data type.');
		}

		atomic_file_put_contents($this->config['cache_path'].$id.'.meta'.$this->suffix, '<?php return '.var_export(['strlen' => strlen($data), 'time' => time(), 'ttl' => (int) $ttl, 'expire' => (time() + $ttl)], true).';');

		$save = (atomic_file_put_contents($this->config['cache_path'].$id.$this->suffix, $data)) ? true : false;

		if ($include && $save) {
			$save = include $this->config['cache_path'].$id.$this->suffix;
		}

		return $save;
	}

	/**
	 *
	 * Delete cache based on key
	 *
	 * @access public
	 *
	 * @param string $id
	 *
	 * @return bool
	 *
	 */
	public function delete(string $id) : bool
	{
		return (isset($this->config['cache_multiple_servers'])) ? $this->multi_delete($id) : $this->single_delete($id);
	}

	/**
	 *
	 * increment - unsupported
	 *
	 * @access public
	 *
	 * @param string $id - unsupported
	 * @param int $offset 1 - unsupported
	 *
	 * @return bool
	 *
	 */
	public function increment(string $id, int $offset = 1) : bool
	{
		return false;
	}

	/**
	 *
	 * decrement - unsupported
	 *
	 * @access public
	 *
	 * @param string $id - unsupported
	 * @param int $offset 1 - unsupported
	 *
	 * @return bool
	 *
	 */
	public function decrement(string $id, int $offset = 1) : bool
	{
		return false;
	}

	/**
	 *
	 * clean
	 *
	 * @access public
	 *
	 * @return bool
	 *
	 */
	public function clean() : bool
	{
		array_map('unlink', glob($this->config['cache_path'].'*'.$this->suffix));

		return true;
	}

	/**
	 *
	 * Get the cache info.
	 *
	 * @access public
	 *
	 * @return array
	 *
	 */
	public function cache_info() : array
	{
		$info = [];

		foreach (glob($this->config['cache_path'].'*.meta'.$this->suffix) as $path) {
			$id = basename($path, '.meta'.$this->suffix);
			$metadata = $this->get_metadata($id);

			$info[$id] = [
				'name'=>realpath($path),
				'server_path'=>$path,
				'size'=>filesize($path),
				'expires'=>$metadata['expire'],
				'created'=>$metadata['time'],
				'ttl'=>$metadata['ttl'],
				'meta'=>$id.'.meta'.$this->suffix,
				'cache'=>$id.$this->suffix,
			];
		}

		return $info;
	}

	/**
	 *
	 * Return detailed information on a specific item in the cache.
	 *
	 * @access public
	 *
	 * @param string $id
	 *
	 * @return mixed
	 *
	 */
	public function get_metadata(string $id)
	{
		return (!is_file($this->config['cache_path'].$id.'.meta'.$this->suffix) || !is_file($this->config['cache_path'].$id.$this->suffix)) ? false : include $this->config['cache_path'].$id.'.meta'.$this->suffix;
	}

	/**
	 *
	 * Is this caching driver supported on the system?
	 * Of course this one is.
	 *
	 * @access public
	 *
	 * @return bool
	 *
	 */
	public function is_supported() : bool
	{
		return true;
	}

	/**
	 *
	 * Handle cache delete request from another server
	 *
	 * @access public
	 *
	 * @param string $request
	 *
	 * @return void
	 *
	 * #### Example
	 * ```php
	 * In the receiving controller end point
	 * ci('cache')->export->endpoint_delete($request);
	 * ```
	 */
	public function endpoint_delete(string $request) : void
	{
		if (!in_array($this->input->ip_address(), $this->config['cache_allowed'])) {
			exit(13);
		}

		list($hmac, $id) = explode(chr(0), hex2bin($request));

		if (md5($this->config['encryption_key'].$id) !== $hmac) {
			exit(13);
		}

		$this->single_delete($id);

		echo $request;

		exit(200);
	}

	/**
	 *
	 * Wrapper function to use this library in a closure fashion
	 *
	 * @access public
	 *
	 * @param string $key
	 * @param callable $closure
	 * @param int $ttl null
	 *
	 * @return mixed
	 *
	 * #### Example
	 * ```php
	 * $cached = ci('cache')->export->cache('foobar',function(){ return 'cache me for 60 seconds!' },60);
	 * ```
	 */
	public function cache(string $key, callable $closure, int $ttl = null)
	{
		if (!$cache = $this->get($key)) {
			$ci = ci();
			$cache = $closure($ci);
			$this->save($key, $cache, $ttl);
		}

		return $cache;
	}

	/**
	 *
	 * Preform a cache key delete
	 *
	 * @access protected
	 *
	 * @param string $id
	 *
	 * @return bool
	 *
	 */
	protected function single_delete(string $id) : bool
	{
		$php_file = $this->config['cache_path'].$id.$this->suffix;
		$meta_file = $this->config['cache_path'].$id.'.meta'.$this->suffix;

		if (file_exists($php_file)) {
			$php_file_deleted = unlink($php_file);
			remove_php_file_from_opcache($php_file);
		}

		if (file_exists($meta_file)) {
			$meta_file_deleted = unlink($meta_file);
			remove_php_file_from_opcache($meta_file);
		}

		return ($php_file_deleted || $meta_file_deleted);
	}

	/**
	 *
	 * Handle calling the other servers to tell them to delete a cache key
	 *
	 * @access protected
	 *
	 * @param string $id
	 *
	 * @return bool
	 *
	 */
	protected function multi_delete(string $id) : bool
	{
		/* get the array of other servers */
		$cache_servers =  $this->config['cache_servers'];

		/* create the hmac key */
		$hmac = bin2hex(md5($this->config['encryption_key'].$id).chr(0).$id);

		/* multiple threaded curl to other web heads */
		$mh = curl_multi_init();

		foreach ($cache_servers as $idx=>$server) {
			$url = 'http'.($this->config['cache_server_secure'] ? 's://' : '://').$server.'/'.trim($this->config['cache_url'], '/').'/'.$hmac;
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
