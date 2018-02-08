<?php
/**
* CodeIgniter Page Caching Class
*
* @package			  Orange
* @subpackage		Libraries
* @author				Don Myers
* @link
*/
class Cache_page extends CI_Driver {
	protected $cache = [];
	protected $config;

	public function __construct(&$cache_config) {
		$this->config = &$cache_config;
	}

	/**
	* Get
	*
	* Look for a value in the cache. If it exists, return the data
	* if not, return FALSE
	*
	* @param  string
	* @return mixed	  value that is stored/FALSE on failure
	*/
	public function get($id) {
		return (isset($this->cache[$id])) ? $this->cache[$id] : false;
	}

	/**
	* Cache Save
	*
	* @param  string  $id	Cache ID
	* @param  mixed	   $data	Data to store
	* @param  int	     $ttl	Length of time (in seconds) to cache the data (unused)
	* @param  bool    $raw	Whether to store the raw value (unused)
	* @return	 bool	   TRUE on success, FALSE on failure
	*/
	public function save($id, $data, $ttl = 0, $raw = false) {
		$this->cache[$id] = $data;

		return true;
	}

	/**
	* Delete from Cache
	*
	* @param   mixed	unique identifier of the item in the cache
	* @return 	bool	true on success/false on failure
	*/
	public function delete($id) {
		unset($this->cache[$id]);

		return true;
	}

	/**
	* Increment a raw value
	*
	* @param  string	$id	Cache ID
	* @param  int	    $offset	Step/value to add
	* @return	 mixed	New value on success or FALSE on failure
	*/
	public function increment($id, $offset = 1) {
		return (int)$this->get($id) + (int)$offset;
	}

	/**
	* Decrement a raw value
	*
	* @param  string  $id	Cache ID
	* @param  int     $offset	Step/value to reduce by
	* @return	 mixed   New value on success or FALSE on failure
	*/
	public function decrement($id, $offset = 1) {
		return (int)$this->get($id) - (int)$offset;
	}

	/**
	* Clean the cache
	*
	* @return bool false on failure/true on success
	*/
	public function clean() {
		$this->cache = [];

		return true;
	}

	/**
	* Cache Info
	*
	* @return	 mixed array
	*/
	public function cache_info() {
		return 'page cache v1.0';
	}

	/**
	* Get Cache Metadata
	*
	* @return	 mixed array
	*/
	public function get_metadata($id) {
		return ['count'=>count($this->cache),'keys'=>array_keys($this->cache)];
	}

	/**
	* is_supported()
	*
	* @return	bool
	*/
	public function is_supported() {
		return true;
	}

	public function cache($key, $closure) {
		if (!$cache = $this->get($key)) {
			$ci = ci();

			$cache = $closure($ci);

			$this->save($key, $cache);
		}

		return $cache;
	}

}
