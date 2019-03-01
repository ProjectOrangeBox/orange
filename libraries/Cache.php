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
 * Extension to the CodeIgniter Cache Library
 *
 * Adds additional request & export cache libraries
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2019
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version v2.0
 * @filesource
 *
 * @uses event Event
 *
 * @config cache_path `ROOTPATH.'/var/cache/'`
 * @config cache_default `dummy`
 * @config cache_backup `dummy`
 * @config cache_ttl `60`
 * @config key_prefix `cache.`
 *
 */
class Cache extends \CI_Cache
{
	/**
	 * errors configuration array
	 *
	 * @var \Cache_request
	 */
	public $request;

	/**
	 * errors configuration array
	 *
	 * @var \Cache_export
	 */
	public $export;

	/**
	 * Orange Event Object
	 *
	 * @var \Event
	 */
	protected $event;

	/**
	 * configuration storage
	 *
	 * @var array
	 */
	protected $config = [];

	/**
	 *
	 * Constructor
	 *
	 * @access public
	 *
	 * @param array $config []
	 *
	 */
	public function __construct(array &$config=[])
	{
		$this->event = &ci('event');

		$this->config = &array_replace(load_config('config', 'config'), (array)$config);

		parent::__construct([
			'adapter'=>$this->config['cache_default'],
			'backup'=>$this->config['cache_backup'],
			'key_prefix'=>$this->config['cache_key_prefix'],
		]);

		/* attach page and export to CodeIgniter cache singleton loaded above */
		$this->request = new Cache_request($this->config, $this);
		$this->export = new Cache_export($this->config, $this);
	}

	/**
	 * Wrapper function to sue the currently loaded cache library in a closure fashion
	 *
	 * @param $key string cache key
	 * @param $closure function to run IF the cached data is not found or has expired
	 * @param $ttl integer time to live if empty it will use the default
	 *
	 * @return mixed cached data
	 *
	 */
	/**
	 *
	 * Wrapper function to use the currently loaded cache library in a closure fashion
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
	 * $cached = ci('cache')->inline('foobar',function(){ return 'cache me for 60 seconds!' },60);
	 * ```
	 */
	public function inline(string $key, callable $closure, int $ttl = null)
	{
		if (!$cache = $this->get($key)) {
			$cache = $closure();
			$ttl = ($ttl) ? (int) $ttl : $this->ttl();
			$this->save($key, $cache, $ttl);
		}

		return $cache;
	}

	/**
	 *
	 * Get the current Cache Time to Live with optional "window" support to negate a cache stamped
	 *
	 * @access public
	 *
	 * @param bool $use_window true
	 *
	 * @return int
	 *
	 */
	public function ttl(bool $use_window = true) : int
	{
		/* get the cache ttl from the config file */
		$cache_ttl = (int)$this->config['cache_ttl'];

		/* are they using the window option? */
		if ($use_window) {
			/* let determine the window size based on there cache time to live length no more than 5 minutes */
			$window = min(300, ceil($cache_ttl * .02));
			/* add it to the cache_ttl to get our "new" cache time to live */
			$cache_ttl += mt_rand(-$window, $window);
		}

		return (int)$cache_ttl;
	}

	/**
	 *
	 * Delete cache records based on dot notation "tags"
	 *
	 * @access public
	 *
	 * @param mixed $args array, period separated list of tags or multiple arguments
	 *
	 * @return Cache
	 *
	 * #### Example
	 * ```php
	 * delete_cache_by_tags('acl','user','roles');
	 * delete_cache_by_tags('acl.user.roles');
	 * delete_cache_by_tags(['acl','user','roles']);
	 * ```
	 */
	public function delete_by_tags($args) : Cache
	{
		/* determine if it's a array, period separated list of tags or multiple arguments */
		if (is_array($args)) {
			$tags = $args;
		} elseif (strpos($args, '.') !== false) {
			$tags = explode('.', $args);
		} else {
			$tags = func_get_args();
		}

		/* log a debug entry */
		log_message('debug', 'delete_cache_by_tags '.implode(', ', $tags));

		/* trigger a event incase somebody else needs to know send in our array of tags by reference */
		$this->event->trigger('delete.cache.by.tags', $tags);

		/* get all of the currently loaded cache driver cache keys */
		$cached_keys = $this->cache_info();

		/* if the cache key has 1 or more matching tag delete the entry */
		if (is_array($cached_keys)) {
			foreach ($cached_keys as $key) {
				if (count(array_intersect(explode('.', $key['name']), $tags))) {
					$this->delete($key['name']);
				}
			}
		}

		return $this;
	}
} /* end class */
