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
 * Cache_request
 *
 * Cache for this request only.
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
class Cache_request extends CI_Driver
{
	/**
	 * Cache storage
	 *
	 * @var array
	 */
	protected $cache = [];

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
	 *
	 * Constructor
	 *
	 * @param array &$config
	 * @param Cache &$parent
	 *
	 */
	public function __construct(&$config, &$parent)
	{
		$this->config = &$config;
		$this->parent = &$parent;
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
		return (isset($this->cache[$id])) ? $this->cache[$id] : false;
	}

	/**
	 *
	 * save an item to the cache store.
	 *
	 * @access public
	 *
	 * @param string $id
	 * @param $data
	 * @param int $ttl - unsupported (entire request)
	 * @param bool $raw false - unsupported
	 *
	 * @return bool
	 *
	 */
	public function save(string $id, $data, int $ttl = null, bool $raw = false) : bool
	{
		$this->cache[$id] = $data;

		return true;
	}

	/**
	 *
	 * delete a specific item from the cache store.
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
		unset($this->cache[$id]);

		return true;
	}

	/**
	 *
	 * Performs atomic incrementation of a raw stored value.
	 *
	 * @access public
	 *
	 * @param string $id
	 * @param int $offset 1
	 *
	 * @return int
	 *
	 */
	public function increment(string $id, int $offset = 1)
	{
		$new_value = (int)$this->get($id) + (int)$offset;

		$this->save($id, $new_value);

		return $new_value;
	}

	/**
	 *
	 * Performs atomic decrementation of a raw stored value.
	 *
	 * @access public
	 *
	 * @param string $id
	 * @param int $offset 1
	 *
	 * @return int
	 *
	 */
	public function decrement(string $id, int $offset = 1)
	{
		$new_value = (int)$this->get($id) - (int)$offset;

		$this->save($id, $new_value);

		return $new_value;
	}

	/**
	 *
	 * ‘clean’ the entire cache.
	 *
	 * @access public
	 *
	 * @return bool
	 *
	 */
	public function clean() : bool
	{
		$this->cache = [];

		return true;
	}

	/**
	 *
	 * This method will return information on the entire cache.
	 *
	 * @access public
	 *
	 * @return array
	 *
	 */
	public function cache_info() : array
	{
		$info = [];

		foreach ($this->cache as $key=>$value) {
			$info[$key] = [
				'value'=>$value,
				'size'=>strlen($value),
				'ttl'=>0,
			];
		}

		return $info;
	}

	/**
	 *
	 * This method will return the size if the cached item.
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
		$value = $this->get($id);
		
		return ($value) ? gettype($value) : false;
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
	 * Wrapper function to use this library in a closure fashion
	 * of course these are request only cached items
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
	 * $cached = ci('cache')->request->cache('foobar',function(){ return 'cache me for 60 seconds!' });
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
} /* end class */
