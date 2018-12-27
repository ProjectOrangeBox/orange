<?php
/**
 * Cache_request
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
 * @help Cache for this request only
 */
class Cache_request extends CI_Driver {
	/**
	 * track if the combined cached configuration has been loaded
	 *
	 * @var boolean
	 */
	protected $cache = [];

	/**
	 * track if the combined cached configuration has been loaded
	 *
	 * @var boolean
	 */
	protected $config;

	protected $parent;

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
	public function __construct(&$config,&$parent) {
		$this->config = &$config;
		$this->parent = &$parent;
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
		return (isset($this->cache[$id])) ? $this->cache[$id] : false;
	}

/**
 * save
 * Insert description here
 *
 * @param $id
 * @param $data
 * @param $ttl
 * @param $raw
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function save($id, $data, $ttl = 0, $raw = false) {
		$this->cache[$id] = $data;
		return true;
	}

/**
 * delete
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
	public function delete($id) {
		unset($this->cache[$id]);
		
		return true;
	}

/**
 * increment
 * Insert description here
 *
 * @param $id
 * @param $offset
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function increment($id, $offset = 1) {
		$new_value = (int)$this->get($id) + (int)$offset;
		
		$this->save($id,$new_value);
		
		return $new_value;
	}

/**
 * decrement
 * Insert description here
 *
 * @param $id
 * @param $offset
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function decrement($id, $offset = 1) {
		$new_value = (int)$this->get($id) - (int)$offset;
		
		$this->save($id,$new_value);
		
		return $new_value;
	}

/**
 * clean
 * Insert description here
 *
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function clean() {
		$this->cache = [];
		
		return true;
	}

/**
 * cache_info
 * Insert description here
 *
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function cache_info() {
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
 * get_metadata
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
	public function get_metadata($id) {
		return ['count'=>count($this->cache),'keys'=>array_keys($this->cache)];
	}

/**
 * is_supported
 * Insert description here
 *
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function is_supported() {
		return true;
	}

/**
 * cache
 * Insert description here
 *
 * @param $key
 * @param $closure
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function cache($key, $closure) {
		if (!$cache = $this->get($key)) {
			$ci = ci();
			$cache = $closure($ci);
			$this->save($key, $cache);
		}
		return $cache;
	}
}
