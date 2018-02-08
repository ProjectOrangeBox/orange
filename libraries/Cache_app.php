<?php
/**
* CodeIgniter Page Caching Class
*
* @package			  Orange
* @subpackage		Libraries
* @author				Don Myers
* @link
*/
class Cache_app extends CI_Driver {
	protected $mode; /* file or database */

	protected $path;

	protected $table;
	protected $database_group;

	protected $database_resource = null;
	protected $changed = false;
	protected $appvar = null;

	public function __construct() {
		$config = get_config();

		$configs = [
			'mode'=>'file',
			'path'=>ROOTPATH.'/var/application_variables.php',
			'table'=>'internal_application_variables',
			'database_group'=>'default',
		];

		foreach ($configs as $name => $default) {
			$key = 'cache_app_'.$name;

			$this->$name = (isset($config[$key])) ? $config[$key] : $default;
		}
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
		if (!$this->appvar) {
			$this->first_access();
		}

		return (isset($this->appvar[$id]) == true) ? $this->appvar[$id] : null;
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
	public function save($id, $data, $ttl = 0, $raw = FALSE) {
		$this->changed = true;

		$this->appvar[$id] = $data;

		return true;
	}

	/**
	* Delete from Cache
	*
	* @param   mixed	unique identifier of the item in the cache
	* @return 	bool	true on success/false on failure
	*/
	public function delete($id) {
		$this->changed = true;

		$success = false;

		if (isset($this->appvar[$id])) {
			unset($this->appvar[$id]);

			$success = true;
		}

		return $success;
	}

	/**
	* Increment a raw value
	*
	* @param  string	$id	Cache ID
	* @param  int	    $offset	Step/value to add
	* @return	 mixed	New value on success or FALSE on failure
	*/
	public function increment($id, $offset = 1) {
		$this->changed = true;

		$int = (int)$this->get($id) + (int)$offset;

		$this->save($id,$int);

		return $int;
	}

	/**
	* Decrement a raw value
	*
	* @param  string  $id	Cache ID
	* @param  int     $offset	Step/value to reduce by
	* @return	 mixed   New value on success or FALSE on failure
	*/
	public function decrement($id, $offset = 1) {
		$this->changed = true;

		$int = (int)$this->get($id) - (int)$offset;

		$this->save($id,$int);

		return $int;
	}

	/**
	* Clean the cache
	*
	* @return bool false on failure/true on success
	*/
	public function clean() {
		$this->changed = true;

		$this->appvar = [];

		return true;
	}

	/**
	* Cache Info
	*
	* @return	 mixed array
	*/
	public function cache_info() {
		return 'application permanent cache v1.0';
	}

	/**
	* Get Cache Metadata
	*
	* @return	 mixed array
	*/
	public function get_metadata($id) {
		return ['count'=>count($this->appvar),'keys'=>array_keys($this->appvar)];
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
			$ci = &ci();

			$cache = $closure($ci);

			$this->save($key, $cache);
		}

		return $cache;
	}

	public function __destruct() {
		/* did anything actually change? */
		if ($this->changed) {
			switch ($this->mode) {
				case 'database':
					if (!$this->database_resource) {
						$this->database_resource = ci()->load->database($this->database_group,true);
					}

					/* we store everything 1 record (id 1) */
					$this->database_resource->replace($this->table,['id'=>1,'value'=>json_encode((array)$this->appvar)]);

	        ci()->cache->delete($this->table);
				break;
				case 'file':
					atomic_file_put_contents($this->path,'<?php return '.str_replace('stdClass::__set_state', '(object)', var_export($this->appvar, true)).';');
				break;
			}
		}
	}

	protected function first_access() {
		$this->appvar = [];

		switch ($this->mode) {
			case 'database':
				if (!$value = ci()->cache->get($this->table)) {
					$this->database_resource = ci()->load->database($this->database_group,true);

					/* we store everything 1 record (id 1) */
					$dbc = $this->database_resource->get_where($this->table,['id'=>1]);

					$dbr = $dbc->row();

					$value = $dbr->value;

	        ci()->cache->save($this->table,$value,ttl());
				}

				$this->appvar = json_decode($value,true);
			break;
			case 'file':
				if (file_exists($this->path)) {
					$this->appvar = include $this->path;
				}
			break;
		}
	}
}
