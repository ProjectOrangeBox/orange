<?php
/**
* A base Database model with a series of CRUD functions (powered by CI's query builder),
* validation-in-model support, events and more.
*
* Orange Framework Extension
*
* This content is released under the MIT License (MIT)
*
* @package	CodeIgniter / Orange
* @author Don Myers
* @license http://opensource.org/licenses/MIT MIT License
* @link https://github.com/ProjectOrangeBox
*
* Based on Original Work by Jamie Rumbelow
* @link http://github.com/jamierumbelow/codeigniter-base-model
* @copyright Copyright (c) 2012,Jamie Rumbelow <http://jamierumbelow.net>
*
*
* required
* core:
* libraries:
* models:
* helpers:
* functions: delete_cache_by_tags
*
*/

class Database_model extends MY_Model {
	use Database_extras_model_trait;

	/**
	* The database connection object. Will be set to the default
	* connection. This allows individual models to use different DBs
	* without overwriting CI's global $this->db connection.
	*/
	protected $_database; /* connection to database resource */

	protected $db_group = null; /* database config group to use */
	
	protected $table; /* table name - this is also used as the resource object name */
	protected $protected = []; /* protect these columns from auto set */
	
	protected $debug = false; /* path to debug file to write - used naturally for local debugging */

	/* This model's default primary key or unique identifier. Used by the get(),update() and delete() functions. */
	protected $primary_key = 'id';
	
	/* auto remove the primary key from inserts */
	protected $remove_primary_on_insert = true;

	protected $single_column_name = null;

	protected $cache_prefix; /* this is auto generated in the constructor */
	protected $additional_cache_tags = ''; /* example 'tag1.tag2.tag' */
	
	protected $skip_rules = false; /* wether to skip all rule validation (probably because you don't have rules) */
	
	protected $default_return_on_multi;
	protected $default_return_on_single;
	
	protected $uses = [];
	protected $trait_events = [
		'get where'=>[],

		'add fields insert'=>[],
		'add fields update'=>[],
		'add fields delete'=>[],

		'before select'=>[],
		'before delete'=>[],
		'before update'=>[],
		'before insert'=>[],
	];
	
	/* Initialize the model, tie into the CodeIgniter super-object */
	public function __construct() {
		parent::__construct();
		
		/* defaults return types */
		$this->default_return_on_multi = [];
		$this->default_return_on_single = new stdClass();

		/* the parent model expects a more generic object "name" */
		$this->object = strtolower($this->table);

		$this->cache_prefix = trim('database.' . $this->object . '.' . trim($this->additional_cache_tags,'.'),'.');

		/* use a custom database connection or default? */
		if (isset($this->db_group)) {
			$this->_database = $this->load->database($this->db_group, true);
		} else {
			$this->_database = $this->db;
		}

		$this->uses = class_uses($this,false);
		
		/* give the traits a chance to do some __construct stuff */
		foreach ($this->uses as $use) {
			$method = $use.'__construct';
		
			if (method_exists($this,$method)) {
				log_message('info', $method);
		
				$this->$method();
			}
		}

		log_message('info', 'Database_model Class Initialized');
	}
	
	/* adding this make "most" other database methods work */
	public function __call($name, $arguments) {
		if (method_exists($this->_database,$name)) {
			call_user_func_array([$this->_database,$name],$arguments);
		} else {
			throw new Exception("Database Model method $name not found");
		}
		
		return $this;
	}

	public function get_cache_prefix() {
		return $this->cache_prefix;
	}

	public function get_tablename() {
		return $this->table;
	}

	public function get_primary_key() {
		return $this->primary_key;
	}

	public function column($name) {
		$this->single_column_name = $name;

		return $this;
	}

	public function empty_many_returns($returns){
		$this->default_return_on_multi = $returns;
		
		return $this;
	}
	
	public function empty_returns($returns) {
		$this->default_return_on_single = $returns;

		return $this;
	}

	public function get($primary_value = null) {
		return ($primary_value === null) ? $this->default_return_on_single : $this->get_by([$this->primary_key => $primary_value]);
	}

	public function get_by($where = null) {
		if ($where) {
			$this->_database->where($where);
		}

		return $this->_get(false);
	}

	public function get_many() {
		return $this->get_many_by();
	}

	public function get_many_by($where = null) {
		if ($where) {
			$this->_database->where($where);
		}
		
		foreach ($this->trait_events['get where'] as $event) $event();

		return $this->_get(true);
	}

	public function insert($data) {
		foreach ($this->trait_events['before insert'] as $event) $event();

		$data = (array)$data;

		/* unset the primary key if it's set in the data array */
		if (isset($data[$this->primary_key]) && $this->remove_primary_on_insert) {
			unset($data[$this->primary_key]);
		}

		$success = (!$this->skip_rules) ? $this->only_columns_with_rules($data)->validate($data) : true;

		if ($success) {
			/* remove the protected columns */
			$this->remove_columns($data, $this->protected);

			foreach ($this->trait_events['add fields insert'] as $event) $event($data);

			if (count($data)) {
				$this->_database->insert($this->table, $data);
			}

			/* delete cache data with same cache prefix as this object */
			$this->delete_cache_by_tags()->_log_last_query();

			$success = (int) $this->_database->insert_id();
		}

		return $success;
	}

	public function update($data) {
		$data = (array)$data;
		
		/* is the primary key present? */
		if (!isset($data[$this->primary_key])) {
			show_error('Database Model update primary key missing');
		}

		return $this->update_by($data, [$this->primary_key => $data[$this->primary_key]]);
	}

	public function update_by($data, $where = []) {
		foreach ($this->trait_events['before update'] as $event) $event();

		$data = (array)$data;

		$success = (!$this->skip_rules) ? $this->only_columns_with_rules($data)->validate($data) : true;

		/*
		unset the primary key field because we are either
		using that or don't want a mass update on the
		primary which will cause a error because it should be unique
		*/
		unset($data[$this->primary_key]);

		if ($success) {
			/* remove the protected columns */
			$this->remove_columns($data, $this->protected);

			foreach ($this->trait_events['add fields update'] as $event) $event($data);
			
			if (count($data)) {
				$this->_database->where($where)->update($this->table, $data);
			}

			/* delete cache data with same cache prefix as this object */
			$this->delete_cache_by_tags()->_log_last_query();

			$success = (int) $this->_database->affected_rows();
		}

		return $success;
	}

	public function delete($data) {
		if (is_object($data) || is_array($data)) {
			$data = (array)$data;

			/* is the primary key present? */
			if (!isset($data[$this->primary_key])) {
				show_error('Database Model delete primary key missing');
			}
		} elseif (is_scalar($data)) {
			$data[$this->primary_key] = $data;
		}
		
		return $this->delete_by($data);
	}

	public function delete_by($data) {
		foreach ($this->trait_events['before delete'] as $event) $event();

		$data = (array)$data;

		$success = (!$this->skip_rules) ? $this->validate($data) : true;

		if ($success) {
			$this->_database->where($data)->delete($this->table);

			$this->delete_cache_by_tags()->_log_last_query();

			$success = (int) $this->_database->affected_rows();
		}

		return $success;
	}

	public function format_result($dbc, $as_array = true) {
		$result = ($as_array) ? $this->_as_array($dbc) : $this->_as_row($dbc);

		if ($as_array == false && $this->single_column_name && is_object($result)) {
			$result = $result->{$this->single_column_name};
		}

		/* clear this out to not interfere with the next query */
		$this->single_column_name = false;

		return $result;
	}

	protected function _log_last_query() {
		if ($this->debug) {
			$query  = $this->_database->last_query();
			$output = (is_array($query)) ? print_r($query, true) : $query;
			file_put_contents(ROOTPATH.'/var/logs/database.'.__CLASS__.'.'.$this->debug,$output.chr(10), FILE_APPEND);
		}
		
		return $this;
	}

	protected function _get($as_array = true, $table = null) {
		foreach ($this->trait_events['before select'] as $event) $event();

		$table = ($table) ? $table : $this->table;

		$dbc = $this->_database->get($table);

		$this->_log_last_query();

		/* get returns a single object so return the first record or an empty record */
		return $this->format_result($dbc, $as_array);
	}

	protected function _as_array($dbc) {
		$result = $this->default_return_on_multi;

		/* multiple records */
		if (is_object($dbc)) {
			if ($dbc->num_rows()) {
				$result = $dbc->result();
			}
		}
	
		return $result;
	}
	
	protected function _as_row($dbc) {
		$result = $this->default_return_on_single;
	
		if (is_object($dbc)) {
			if ($dbc->num_rows()) {
				$result = $dbc->row();
			}
		}
		
		return $result;
	}

} /* end class */