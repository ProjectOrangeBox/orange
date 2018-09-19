<?php

class Cache extends CI_Cache {
	public $request;
	public $export;
	public $application;

	protected $event;

	protected $config = [];

	public function __construct($config=[]) {
		$this->event = &ci('event');

		$this->config = &array_replace(load_config('config','config'),(array)$config);

		parent::__construct([
			'adapter'=>$this->config['cache_default'],
			'backup'=>$this->config['cache_backup'],
			'key_prefix'=>$this->config['cache_key_prefix'],
		]);

		/* attach page and export to CodeIgniter cache singleton loaded above */
		$this->request = new Cache_request($this->config,$this);
		$this->export = new Cache_export($this->config,$this);
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
	public function inline($key, $closure, $ttl = null) {
		if (!$cache = $this->get($key)) {
			$cache = $closure();
			$ttl = ($ttl) ? (int) $ttl : $this->ttl();
			$this->save($key, $cache, $ttl);
		}

		return $cache;
	}

	/**
	 * Get the current Cache Time to Live with optional "window" support to negate a cache stamped
	 *
	 * @param $use_window boolean
	 *
	 * @return integer
	 *
	 */
	public function ttl($use_window=true) {
		/* get the cache ttl from the config file */
		$cache_ttl = (int)$this->config['cache_ttl'];

		/* are they using the window option? */
		if ($use_window) {
			/* let determine the window size based on there cache time to live length no more than 5 minutes */
			$window = min(300,ceil($cache_ttl * .02));
			/* add it to the cache_ttl to get our "new" cache time to live */
			$cache_ttl += mt_rand(-$window,$window);
		}

		return (int)$cache_ttl;
	}

	/**
	 * Delete cache records based on dot notation "tags"
	 *
	 * @param $args mixed array, period separated list of tags or multiple arguments
	 *
	 * @return
	 *
	 * @example delete_cache_by_tags('acl','user','roles');
	 * @example delete_cache_by_tags('acl.user.roles');
	 * @example delete_cache_by_tags(['acl','user','roles']);
	 */
	public function delete_by_tags($args) {
		/* determine if it's a array, period separated list of tags or multiple arguments */
		if (is_array($args)) {
			$tags = $args;
		} elseif(strpos($args,'.') !== false) {
			$tags = explode('.', $args);
		} else {
			$tags = func_get_args();
		}

		/* log a debug entry */
		log_message('debug', 'delete_cache_by_tags '.implode(', ', $tags));

		/* trigger a event incase somebody else needs to know send in our array of tags by reference */
		$this->event->trigger('delete.cache.by.tags',$tags);

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
