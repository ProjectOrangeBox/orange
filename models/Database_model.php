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
 * Database Base Model
 *
 * Provides support for
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2019
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version v2.0
 * @filesource
 *
 * @throws Exception
 *
 */
class Database_model extends \MY_Model
{
	/**
	 * Database config to use for _database if other than default
	 *
	 * @var string
	 */
	protected $db_group = null;

	/**
	 * Database configuration to use for _database on read operations if other than default
	 *
	 * @var string
	 */
	protected $read_db_group = null;

	/**
	 * Database configuration to use for _database on write operations if other than default
	 *
	 * @var string
	 */
	protected $write_db_group = null;

	/**
	 * Database models table name
	 *
	 * @var string
	 */
	protected $table;

	/**
	 * Columns removed from the data array on update and insert statements
	 *
	 * @var array
	 */
	protected $protected = [];

	/**
	 * If true a log will be generated LOGPATH folder under the table name
	 *
	 * @var boolean
	 */
	protected $debug = false;

	/**
	 * Default primary id without one many automatic methods will not work
	 *
	 * @var string
	 */
	protected $primary_key = 'id'; /*  */

	/**
	 * Additional cache tags to add to cache prefix remember each tag is separated by periods
	 *
	 * @var string
	 */
	protected $additional_cache_tags = '';

	/**
	 * String name of Entity in entities folder in models folder
	 *
	 * @var mixed
	 */
	protected $entity = null;

	/**
	 * Reference to the attached empty entity
	 *
	 * @var object
	 */
	protected $entity_class = null;

	/**
	 * Array of additional features
	 * defaults to boolean false to disable feature
	 * each feature has different requirements to enable
	 * but, for column name features you must provide the column name as a string
	 *
	 * @var array
	 */
	protected $has = [
		'read_role'=>false,
		'edit_role'=>false,
		'delete_role'=>false,
		'created_by'=>false,
		'created_on'=>false,
		'created_ip'=>false,
		'updated_by'=>false,
		'updated_on'=>false,
		'updated_ip'=>false,
		'deleted_by'=>false,
		'deleted_on'=>false,
		'deleted_ip'=>false,
		'is_deleted'=>false,
	];

	/**
	 * internal storage of formatted cache prefixes
	 *
	 * @var string
	 */
	protected $cache_prefix;

	/**
	 * Used to determine if the primary key is auto generated then remove it from insert commands
	 *
	 * @var boolean
	 */
	protected $auto_generated_primary = true;

	/**
	 * Internal reference to the database connection
	 *
	 * @var object
	 */
	protected $_database; /*  */

	/**
	 * internal reference to write database connection
	 *
	 * @var object
	 */
	protected $read_database = null;

	/**
	 * internal reference to read database connection
	 *
	 * @var object
	 */
	protected $write_database = null;

	/**
	 * internal storage of a single column name when a single column from a single record is requested
	 *
	 * @var string
	 */
	protected $temporary_column_name = null;

	/**
	 * internal storage to let the database class know to return an array based instead of a class based database cursor
	 *
	 * @var mixed
	 */
	protected $temporary_return_as_array = null;

	/**
	 * internal storage of what to return when nothing is found on select for multiple records
	 *
	 * @var mixed
	 */
	protected $default_return_on_many;

	/**
	 * internal storage of what to return when nothing is found on select for a single record
	 *
	 * @var mixed
	 */
	protected $default_return_on_single;

	/**
	 * internal storage of whether we should skip rule validation
	 *
	 * @var boolean
	 */
	protected $skip_rules = false;

	/**
	 * internal storage whether to ignore read role
	 *
	 * @var boolean
	 */
	protected $ignore_read_role = false;

	/**
	 * internal storage whether to ignore edit role
	 *
	 * @var boolean
	 */
	protected $ignore_edit_role = false;

	/**
	 * internal storage whether to ignore delete role
	 *
	 * @var boolean
	 */
	protected $ignore_delete_role = false;

	/**
	 * internal storage whether to limit the query (select)
	 *
	 * @var integer
	 */
	protected $limit_to = false;

	/**
	 * internal storage to soft deleted where clause
	 *
	 * @var mixed false or array
	 */
	protected $deleted_where_clause = false;

	/**
	 *
	 * Constructor
	 *
	 * @access public
	 *
	 */
	public function __construct()
	{
		/* setup MY_Model */
		parent::__construct();

		if (empty($this->table)) {
			throw new \Exception('Database model table not specified.');
		}

		/* models aren't always database tables so set the object name to the table name */
		$this->object = strtolower($this->table);

		/* setup the cache prefix for this model so we can flush the cache based on tags */
		$this->cache_prefix = trim('database.'.$this->object.'.'.trim($this->additional_cache_tags, '.'), '.');

		/* is db group set? then that's the connection config we will use */
		if (isset($this->db_group)) {
			log_message('debug', 'Database Model using "'.$this->object.'::'.$this->db_group.'" connection.');

			/* use our specified connection */
			$this->_database = &$this->load->database($this->db_group, true);
			$this->read_database = &$this->_database;
			$this->write_database = &$this->_database;
		} else {
			log_message('debug', 'Database Model using '.$this->object.'::default connection.');

			/* use default */
			$this->_database = &$this->db;
			$this->read_database = &$this->_database;
			$this->write_database = &$this->_database;
		}

		/* is read db group set? then that's the connection config we will use for reads */
		if (isset($this->read_db_group)) {
			log_message('debug', 'Database Model using read "'.$this->object.'::'.$this->db_group.'" connection.');

			$this->read_database = $this->load->database($this->read_db_group, true);
		}

		/* is write db group set? then that's the connection config we will use for writes */
		if (isset($this->write_db_group)) {
			log_message('debug', 'Database Model using write "'.$this->object.'::'.$this->db_group.'" connection.');

			$this->write_database = $this->load->database($this->write_db_group, true);
		}

		if ((!isset($this->read_database) && !isset($this->write_database)) || !isset($this->_database)) {
			throw new \Exception('Database Model could not attach to database.');
		}

		/* Reset Orange Database Model Query Builder and CodeIgniter Query Builder */
		$this->reset_query();

		/* Is there are record entity attached? */
		if ($this->entity) {
			$this->entity_class = ci('load')->entity($this->entity, $this);

			$this->default_return_on_single =& $this->entity_class;
		} else {
			/* on single record return a class */
			$this->default_return_on_single = new stdClass();
		}

		log_message('info', 'Database_model Class Initialized');
	}

	/**
	 *
	 * Try to call CodeIgniter Database methods on our _database object
	 * or throw a error if it's a invalid method
	 * note: use the switch_db method before using this if you are using different read and write databases
	 *       so the correct CodeIgniter's Query Builder is being used.
	 *
	 * @access public
	 *
	 * @param string $name
	 * @param array $arguments
	 *
	 * @throws Exception
	 * @return Database_model
	 *
	 */
	public function __call(string $name, array $arguments) : Database_model
	{
		/* pass thru */
		if (method_exists($this->_database, $name)) {
			call_user_func_array([$this->_database,$name], $arguments);
		} else {
			throw new \Exception('Unknown method "'.$name.'".');
		}

		return $this;
	}

	/**
	 *
	 * Reset the model for another Query
	 *
	 * @access public
	 *
	 * @return Database_model
	 *
	 */
	public function reset_query() : Database_model
	{
		/* Reset the CodeIgniter Query Builder */
		$this->_database->reset_query();

		/* and now ours */
		$this->temporary_column_name = null;
		$this->temporary_return_as_array = null;
		$this->default_return_on_many = [];
		$this->default_return_on_single = ($this->entity_class) ? $this->entity_class : new stdClass();
		$this->ignore_read_role = false;
		$this->ignore_edit_role = false;
		$this->ignore_delete_role = false;
		$this->skip_rules = false;
		$this->limit_to = false;

		$this->deleted_where_clause = [$this->has['is_deleted'] => 0];

		return $this;
	}

	/**
	 *
	 * Informs the where clause to not include the read role rules condition
	 *
	 * @access public
	 *
	 * @return Database_model
	 *
	 */
	public function ignore_read_role() : Database_model
	{
		$this->ignore_read_role = true;

		return $this;
	}

	/**
	 *
	 * Informs the where clause to not include the edit role rules condition
	 *
	 * @access public
	 *
	 * @return Database_model
	 *
	 */
	public function ignore_edit_role() : Database_model
	{
		$this->ignore_edit_role = true;

		return $this;
	}

	/**
	 *
	 * Informs the where clause to not include the delete role condition
	 *
	 * @access public
	 *
	 * @return Database_model
	 *
	 */
	public function ignore_delete_role() : Database_model
	{
		$this->ignore_delete_role = true;

		return $this;
	}

	/**
	 *
	 * Return the current cache prefix
	 *
	 * @access public
	 *
	 * @return string
	 *
	 */
	public function get_cache_prefix() : string
	{
		return (string)$this->cache_prefix;
	}

	/**
	 * Return the current table name
	 *
	 * @access public
	 *
	 * @return string
	 *
	 */
	public function get_tablename() : string
	{
		return (string)$this->table;
	}

	/**
	 * Return the current primary key
	 *
	 * @access public
	 *
	 * @return string
	 *
	 */
	public function get_primary_key() : string
	{
		return (string)$this->primary_key;
	}

	/**
	 * Return if this table uses soft deletes
	 *
	 * @access public
	 *
	 * @return boolean
	 *
	 */
	public function get_soft_delete() : bool
	{
		return (is_string($this->has['is_deleted']));
	}

	/**
	 * Informs the class to return an array overriding what ever is currently set as default
	 *
	 * @access public
	 *
	 * @return Database_model
	 *
	 */
	public function as_array() : Database_model
	{
		$this->temporary_return_as_array = true;

		return $this;
	}

	/**
	 * Return only this single column value from single record
	 *
	 * @param string $name name of the single column value to return
	 *
	 * @return Database_model
	 *
	 */
	public function column(string $name) : Database_model
	{
		$this->temporary_column_name = (string)$name;
		$this->limit_to = 1;

		return $this;
	}

	/**
	 * If no record(s) are found return this
	 *
	 * @param mixed $return
	 *
	 * @return Database_model
	 *
	 */
	public function on_empty_return($return) : Database_model
	{
		$this->default_return_on_single	= $return;
		$this->default_return_on_many	= $return;

		return $this;
	}

	/**
	 * Get a single record
	 * if nothing provided than return the value for default on single record
	 *
	 * @param mixed $primary_value
	 *
	 * @return mixed
	 *
	 */
	public function get($primary_value = null)
	{
		$this->limit_to = 1;

		return ($primary_value === null) ? $this->default_return_on_single : $this->get_by([$this->primary_key => $primary_value]);
	}

	/**
	 * Get a single record using an where clause
	 *
	 * @param array $where CodeIgniter builder compatible where clause
	 *
	 * @see https://www.codeigniter.com/user_guide/database/query_builder.html#looking-for-specific-data
	 *
	 * @return mixed
	 *
	 */
	public function get_by(array $where = null)
	{
		$this->limit_to = 1;

		if ($where) {
			$this->_database->where($where);
		}

		/* get the results as a record */
		return $this->_get(false);
	}

	/**
	 * Get multiple records no filter
	 *
	 * @return mixed
	 *
	 */
	public function get_many()
	{
		return $this->get_many_by();
	}

	/**
	 * Get multiple records using a filter
	 *
	 * @param array $where CodeIgniter builder compatible where clause
	 *
	 * @see https://www.codeigniter.com/user_guide/database/query_builder.html#looking-for-specific-data
	 *
	 * @return mixed
	 *
	 */
	public function get_many_by(array $where = null)
	{
		if ($where) {
			$this->_database->where($where);
		}

		/* get the results as an array of records */
		return $this->_get(true);
	}

	/**
	 * Insert a database record
	 *
	 * @param array $data array of key (column name) value pairs
	 *
	 * @return mixed - false on failure or the insert id
	 *
	 */
	public function insert(array $data)
	{
		/* switch to the write database if we are using 2 different connections */
		$this->switch_database('write');

		/* convert the input to any array if it's not already */
		$data = (array)$data;

		/* is there are auto generated primary key? */
		if ($this->auto_generated_primary) {
			/* yes - then remove the column if it's provided */
			unset($data[$this->primary_key]);
		}

		/* preform the validation if there are rules and skip rules is false only using the data input that has rules using the insert rule set */
		$success = (!$this->skip_rules && count($this->rules)) ? $this->add_additional_rules()->only_columns($data, $this->rules)->add_rule_set_columns($data, 'insert')->validate($data) : true;

		/* if the validation was successful then proceed */
		if ($success) {
			/*
			remap any data field columns to actual database columns - this way form name can be different than actual database column names
			remove the protected columns - remove any columns which are never inserted into the database (perhaps database generated columns)
			call the add field on insert method which can be overridden on the extended model class
			call the add where on insert method which can be overridden on the extended model class
			 */

			$this
				->remap_columns($data, $this->rules)
				->remove_columns($data, $this->protected)
				->add_fields_on_insert($data)
				->add_where_on_insert($data);

			/* are there any columns left? */
			if (count($data)) {
				/* yes - run the actual CodeIgniter Database insert */
				$this->_database->insert($this->table, $data);

				/**
				 * set success to the insert id - if there is no auto generated primary if 0 is
				 * returned so exact (===) should be used on the results to determine if it's "really" a error (false)
				 */
				$success = $this->_database->insert_id();
			}

			/* ok now delete any caches since we did a insert and log it if we need to */
			$this->delete_cache_by_tags()->log_last_query();
		}

		/* clear the temp stuff */
		$this->reset_query();

		/* return false on error or the primary id of the auto generated primary if if there is no auto generated primary if 0 is returned */
		return $success;
	}

	/**
	 * Update a database record
	 *
	 * @param array $data array of key (column name) value pairs
	 *
	 * @throws Exception
	 * @return mixed - false on fail or the number of affected rows
	 *
	 */
	public function update(array $data)
	{
		/* convert the input to any array if it's not already */
		$data = (array)$data;

		/* the primary key must be set to use this command */
		if (!isset($data[$this->primary_key])) {
			/* if not than throw error */
			throw new \Exception('Database Model update primary key missing');
		}

		/* call by using the primary key */
		return $this->update_by($data, [$this->primary_key => $data[$this->primary_key]]);
	}

	/**
	 * Update a database record based on the name value associated array pairs using a where clause
	 *
	 * @param array $data array of key (column name) value pairs
	 * @param array $where CodeIgniter builder compatible where clause
	 *
	 * @see https://www.codeigniter.com/user_guide/database/query_builder.html#looking-for-specific-data
	 *
	 * @return mixed - false on fail or the affected rows
	 *
	 */
	public function update_by(array $data, array $where = [])
	{
		/* switch to the write database if we are using 2 different connections */
		$this->switch_database('write');

		/* convert the input to any array if it's not already */
		$data = (array)$data;

		/* preform the validation if there are rules and skip rules is false only using the data input that has rules using the update rule set */
		$success = (!$this->skip_rules && count($this->rules)) ? $this->add_additional_rules()->only_columns($data, $this->rules)->add_rule_set_columns($data, 'update')->validate($data) : true;

		/* always remove the primary key */
		unset($data[$this->primary_key]);

		/* if the validation was successful then proceed */
		if ($success) {
			/*
			remap any data field columns to actual data base columns
			remove the protected columns
			call the add field on update method which can be overridden on the extended class
			call the add where on update method which can be overridden on the extended class
			 */
			$this
				->remap_columns($data, $this->rules)
				->remove_columns($data, $this->protected)
				->add_fields_on_update($data)
				->add_where_on_update($data);

			/* are there any columns left? */
			if (count($data)) {
				/* yes - run the actual CodeIgniter Database update */
				$this->_database->where($where)->update($this->table, $data);
			}

			/* ok now delete any caches since we did a update and log it if we need to */
			$this->delete_cache_by_tags()->log_last_query();

			/* set success to the affected rows returned */
			$success = (int) $this->_database->affected_rows();
		}

		/* clear the temp stuff */
		$this->reset_query();

		/* return false on error or 0 (also false) if no rows changed */
		return $success;
	}

	/**
	 * Delete based on primary key
	 *
	 * @param $arg single value which will automatically be used with the primary key or an actual CodeIgniter where clause
	 *
	 * @see https://www.codeigniter.com/user_guide/database/query_builder.html#looking-for-specific-data
	 *
	 * @return mixed - false on fail or the affected rows
	 *
	 */
	public function delete($arg)
	{
		$where = $this->create_where($arg, true);

		return $this->delete_by($where);
	}

	/**
	 *
	 * Delete Record with support for soft deletes if configured
	 *
	 * @access public
	 *
	 * @param array $data array of key (column name) value pairs
	 * @param array $where CodeIgniter builder compatible where clause
	 *
	 * @see https://www.codeigniter.com/user_guide/database/query_builder.html#looking-for-specific-data
	 *
	 * @return mixed
	 *
	 */
	public function delete_by(array $data, array $where = null)
	{
		/* switch to the write database if we are using 2 different connections */
		$this->switch_database('write');

		/* first we need to either build the where or use the where included */
		$where = ($where) ?? $data;

		/* preform the validation if there are rules and skip rules is false only using the data input that has rules using the delete rule set */
		$success = (!$this->skip_rules && count($this->rules)) ? $this->only_columns($data, $this->rules)->add_rule_set_columns($data, 'delete')->validate($data) : true;

		/* if the validation was successful then proceed */
		if ($success) {
			/**
			 * call the add field on delete method which can be overridden on the extended class
			 * remap any data field columns to actual data base columns
			 */
			$this->remap_columns($data, $this->rules)->add_fields_on_delete($data)->add_where_on_delete($data);

			/* does this model support soft delete */
			if ($this->has['is_deleted']) {
				/* preform the actual CodeIgniter Database soft delete */
				$this->_database->where($where)->set($data)->update($this->table);
			} else {
				/* preform the actual CodeIgniter Database Delete */
				$this->_database->where($where)->delete($this->table);
			}

			/* ok now delete any caches since we did a delete and log it if we need to */
			$this->delete_cache_by_tags()->log_last_query();

			/* set success to the affected rows returned */
			$success = (int) $this->_database->affected_rows();
		}

		/* clear the temp stuff */
		$this->reset_query();

		/* return false on error or 0 (also false) if no rows changed */
		return $success;
	}

	/**
	 * Convert Database Cursor into a useable record
	 *
	 * @param $dbc database cursor
	 *
	 * @return $mixed
	 *
	 */
	protected function _as_row($dbc)
	{
		/* setup default if empty */
		$result = $this->default_return_on_single;

		/* is the cursor actually an object? */
		if (is_object($dbc)) {
			/* 1 or more rows found? */
			if ($dbc->num_rows()) {
				/* yes - ok let's return a entity, array, or object */
				if ($this->entity && $this->temporary_return_as_array !== true) {
					$result = $dbc->custom_row_object(0, $this->entity);
				} elseif ($this->temporary_return_as_array) {
					$result = $dbc->row_array();
				} else {
					$result = $dbc->row();
				}
			}
		}

		return $result;
	}

	/**
	 * Catalog provides a simple way and interface to make a simple query
	 *
	 * @param string $array_key - column name to use for the catalog arrays associate key
	 * @param mixed $select_columns table columns names | * (all) | null (all)
	 * @param array $where CodeIgniter Database Where key=>value
	 * @param string $order_by CodeIgniter table column name | column name and direction
	 * @param mixed $cache_key if provided cache output string or array or bool (true) to auto create a key based on the passed parameters
	 * @param bool $with_deleted [false] with the soft deleted records?
	 * @param bool $ignore_read [false] should we ignore row level read permissions?
	 *
	 * @return array records as objects
	 *
	 */
	public function catalog(string $array_key = null, $select_columns = null, array $where = null, string $order_by = null, $cache_key = null, bool $with_deleted = false, bool $ignore_read = false) : array
	{
		/**
		 * if they provide a cache key then we will cache the responds
		 * Note: roles may affect the select statement so that must be taken into account
		 */
		$results = false;

		/* if cache key is really really true */
		if ($cache_key === true) {
			$cache_key = func_get_args();
		}

		/* if it's a array from above or sent in directly */
		if (is_array($cache_key)) {
			$cache_key = md5(json_encode($cache_key));
		}

		/* if it's a string from above or sent in directly */
		if (is_string($cache_key)) {
			$results = ci('cache')->get($this->cache_prefix.'.'.$cache_key);
		}

		/* if we didn't get results as a array then we need to run the catalog query */
		if (!is_array($results)) {
			if ($with_deleted) {
				$this->with_deleted();
			}

			$has_read_role = $this->has['read_role'];

			if ($ignore_read) {
				$this->has['read_role'] = false;
			}

			/* we aren't looking for a single column by default */
			$single_column = false;

			/* if array_key is empty then use the primary key */
			$array_key = ($array_key) ? $array_key : $this->primary_key;

			/* are the select columns a comma sep. array or array already? */
			$select_columns = is_array($select_columns) ? implode(',', $select_columns) : $select_columns;

			/* if select columns is null or * (all) then select is all */
			if ($select_columns === null || $select_columns == '*') {
				$select = '*';
			} else {
				/* format the select to a comma sep list and add array key if needed */
				$select = $array_key.','.$select_columns;
				if (strpos($select_columns, ',') === false) {
					$single_column = $select_columns;
				}
			}

			/* apply the select column */
			$this->_database->select($select);

			/* does where contain anything? if so apply the where clause */
			if ($where) {
				$this->_database->where($where);
			}

			/* does order by contain anything? if so apply it */
			if ($order_by) {
				$order_by = trim($order_by);
				if (strpos($order_by, ' ') === false) {
					$this->_database->order_by($order_by);
				} else {
					list($column, $direction) = explode(' ', $order_by, 2);
					$this->_database->order_by($column, $direction);
				}
			}

			/* get the results as an array of record */
			$dbc = $this->_get(true);

			/* for each returned row format into a simple array with keys and values or complex with keys and array of columns */
			foreach ($dbc as $dbr) {
				if ($single_column) {
					$results[$dbr->$array_key] = $dbr->$single_column;
				} else {
					$results[$dbr->$array_key] = (array)$dbr;
				}
			}

			if ($cache_key) {
				ci('cache')->save($this->cache_prefix.'.'.$cache_key, $results);
			}

			$this->has['read_role'] = $has_read_role;
		}

		/* results MUST be a array even if it's empty */
		if (!is_array($results)) {
			$results = [];
		}

		return $results;
	}

	/**
	 *  Model based is unique for validation rule
	 *
	 * @param string $field value we are testing
	 * @param string $column database column name
	 * @param string $form_key form input key
	 *
	 * @return bool
	 *
	 * $success = ci('foo_model')->is_uniquem('Johnny Appleseed','name','id');
	 *
	 */
	public function is_uniquem(string $field, string $column, string $form_key) : Bool
	{
		/**
		 * run the query
		 * return a maximum of 3 ignore
		 * Ignoring any read roles permissions
		 * if it's not unique then clearly it's it's not unique
		 */
		$dbc = $this->_database->select($column.','.$this->primary_key)->where([$column=>$field])->get($this->table, 3);

		/* how many records where found? */
		$rows_found = $dbc->num_rows();

		/* none? then we are good! */
		if ($rows_found == 0) {
			return true; /* test for really true === */
		}

		/* more than 1? that's really bad return false */
		if ($rows_found > 1) {
			return false; /* test for really false === */
		}

		/* 1 record so do the keys match? */
		return ($dbc->row()->{$this->primary_key} == get_instance()->input->request($form_key));
	}

	/**
	 *
	 * Determine if a record exists
	 *
	 * @access public
	 *
	 * @param mixed $where CodeIgniter builder compatible where clause or primary id (which if you have that then why do you need to determine if the record exists?)
	 *
	 * @see https://www.codeigniter.com/user_guide/database/query_builder.html#looking-for-specific-data
	 *
	 * @return mixed - boolean false or actual record
	 *
	 */
	public function exists($where)
	{
		/* did we get one or more columns */
		return $this->on_empty_return(false)->get_by(((is_array($where)) ? $where : [$this->primary_key=>$where]));
	}

	/**
	 *
	 * Count Total records in table
	 * by default filtering out by read role and soft deleted where applicable
	 *
	 * @access public
	 *
	 * @return int
	 *
	 */
	public function count() : int
	{
		return $this->count_by();
	}

	/**
	 *
	 * Count Total records in table with where clause
	 * by default filtering out by read role and soft deleted where applicable
	 *
	 * @access public
	 *
	 * @param mixed $where CodeIgniter builder compatible where clause or primary id (which if you have that then why do you need to determine if the record exists?)
	 *
	 * @see https://www.codeigniter.com/user_guide/database/query_builder.html#looking-for-specific-data
	 *
	 * @return int
	 *
	 */
	public function count_by(array $where = null) : int
	{
		$this->_database->select("count('".$this->primary_key."') as codeigniter_column_count");

		if ($where) {
			$this->_database->where($where);
		}

		/* get the results as a record */
		$results = $this->_get(false);

		return (int)$results->codeigniter_column_count;
	}

	/**
	 *
	 * Model Method used for generating the models default "index" (GUI Table) view
	 *
	 * @access public
	 *
	 * @param string $order_by
	 * @param int $limit
	 * @param array $where
	 * @param string $select
	 *
	 * @return mixed
	 *
	 */
	public function index(string $order_by = null, int $limit = null, array $where = null, string $select = null)
	{
		if ($order_by) {
			$this->_database->order_by($order_by);
		}

		if ($limit) {
			$this->_database->limit($limit);
		}

		if ($where) {
			$this->_database->where($where);
		}

		if ($select) {
			$this->_database->select($select);
		}

		$this->add_where_on_select();

		/* get the results as an array of record */
		return $this->_get(true);
	}

	/**
	 * do query with soft deleted
	 */
	public function with_deleted() : Database_model
	{
		$this->deleted_where_clause = false;

		return $this;
	}

	/**
	 *
	 * tracker to determine whether to append only delete records to where clause
	 *
	 * @access public
	 *
	 * @return Database_model
	 *
	 */
	public function only_deleted() : Database_model
	{
		$this->deleted_where_clause = [$this->has['is_deleted'] => 1];

		return $this;
	}

	/**
	 *
	 * Restore soft deleted record based on the records primary id
	 *
	 * @access public
	 *
	 * @param $id
	 *
	 * @return int number of rows affected
	 *
	 */
	public function restore($id) : int
	{
		$rows = 0;

		if ($this->has['is_deleted']) {
			/* build data array so add_fields_on_update can modify it if necessary */
			$data = [$this->has['is_deleted'] => 0];

			$this->add_fields_on_update($data)->_database->update($this->table, $data, $this->create_where($id, true));

			$this->delete_cache_by_tags()->log_last_query();

			$rows = (int)$this->_database->affected_rows();
		}

		return (int)$rows;
	}

	public function empty_record()
	{
		return ($this->entity_class) ? $this->entity_class : array_fill_keys(explode(',',$this->rule_sets['insert']),'');
	}

	/**
	 * Preform the actual SQL select
	 *
	 * @param $as_array boolean return as a array
	 * @param $table string optional
	 *
	 * @throws Exception
	 * @return mixed
	 *
	 */
	protected function _get(bool $as_array = true, string $table = null)
	{
		/* switch to the read database if we are using 2 different connections */
		$this->switch_database('read');

		/* figure out the table for the select */
		$table = ($table) ? $table : $this->table;

		/* add the select where - this also makes it easy to override select just by extending this method */
		$this->add_where_on_select();

		/* are we looking for a single column? */
		if ($this->temporary_column_name) {
			/* yes - then tell CodeIgniter to only select that column */
			$this->_database->select($this->temporary_column_name);
		}

		if ($this->limit_to) {
			$this->_database->limit($this->limit_to);
		}

		/* run the actual CodeIgniter query builder select */
		$dbc = $this->_database->get($table);

		/* log it if we need to */
		$this->log_last_query();

		/* what type of results are they looking for? */
		$results = ($as_array) ? $this->_as_array($dbc) : $this->_as_row($dbc);

		/* are they looking for a single column? */
		if ($this->temporary_column_name) {
			/* yes - then the results are that single column */

			/* nothing found therefore there is no column to read */
			if (count($results) == 0) {
				$results = $this->default_return_on_single;
			} else {
				/* more than 1 column found? this shouldn't be possible with the limit used above */
				if (count($results) != 1) {
					throw new \Exception(count($results).' rows found when trying to use column(...). This only works when your query returns a single record.');
				}

				/* ok we got a single column so return the column value */

				/* is it a array or object that was returned */
				if (is_array($results)) {
					$results = $results[$this->temporary_column_name];
				} elseif (is_object($results)) {
					$results = $results->{$this->temporary_column_name};
				}
			}
		}

		/* clear the temp stuff */
		$this->reset_query();

		/* return the results */
		return $results;
	}

	/**
	 * Convert Database Cursor into something useable
	 *
	 * @param $dbc database cursor object
	 *
	 * @return mixed
	 *
	 */
	protected function _as_array($dbc)
	{
		/* setup default if empty */
		$result = $this->default_return_on_many;

		/* is the cursor actually a object? */
		if (is_object($dbc)) {
			/* 1 or more rows found? */
			if ($dbc->num_rows()) {
				/* yes - ok let's return a entity, array, or object */
				if ($this->entity && $this->temporary_return_as_array !== true) {
					$result = $dbc->custom_result_object($this->entity);
				} elseif ($this->temporary_return_as_array) {
					$result = $dbc->result_array();
				} else {
					$result = $dbc->result();
				}
			}
		}

		return $result;
	}

	/**
	 *
	 * switch between read and write database connection if specified on model
	 *
	 * @access protected
	 *
	 * @param string $which [read|write]
	 *
	 * @throws \Exception
	 * @return Database_model
	 *
	 */
	protected function switch_database(string $which) : Database_model
	{
		if (!in_array($which, ['read','write'])) {
			throw new \Exception('Cannot switch database connection '.__CLASS__.' '.$which);
		}

		if ($which == 'read' && $this->read_database) {
			$this->_database = $this->read_database;
		} elseif ($which == 'write' && $this->write_database) {
			$this->_database = $this->write_database;
		}

		return $this;
	}

	/**
	 *
	 * Dynamically build the where clause
	 *
	 * @access protected
	 *
	 * @param mixed $arg
	 * @param bool $primary_id_required determine if the primary id is required in the built where clause [false]
	 *
	 * @throws \Exception
	 * @return Array
	 *
	 */
	protected function create_where($arg, bool $primary_id_required=false) : array
	{
		if (is_scalar($arg)) {
			$where = [$this->primary_key=>$arg];
		} elseif (is_array($arg)) {
			$where = $arg;
		} else {
			throw new \Exception('Unable to determine where clause in "'.__CLASS__.'"');
		}

		if ($primary_id_required) {
			if (!isset($where[$this->primary_key])) {
				throw new \Exception('Unable to determine primary id where clause in "'.__CLASS__.'"');
			}
		}

		return $where;
	}

	/**
	 *
	 * Built and attach where can read clause
	 *
	 * @access protected
	 *
	 * @return Database_model
	 *
	 */
	protected function where_can_read() : Database_model
	{
		if (!$this->ignore_read_role) {
			if ($this->has['read_role']) {
				$this->_database->where_in($this->has['read_role'], $this->get_user_roles());
			}
		}

		$this->ignore_read_role = false;

		return $this;
	}

	/**
	 *
	 * Built and attach where can edit clause
	 *
	 * @access protected
	 *
	 * @param array $data
	 *
	 * @return Database_model
	 *
	 */
	protected function where_can_edit(array &$data) : Database_model
	{
		if (!$this->ignore_edit_role) {
			if ($this->has['edit_role']) {
				$this->_database->where_in($this->has['edit_role'], $this->get_user_roles());
			}
		}

		$this->ignore_edit_role = false;

		return $this;
	}

	/**
	 *
	 * Built and attach where can delete clause
	 *
	 * @access protected
	 *
	 * @param array $data
	 *
	 * @return Database_model
	 *
	 */
	protected function where_can_delete(array &$data) : Database_model
	{
		if (!$this->ignore_delete_role) {
			if ($this->has['delete_role']) {
				$this->_database->where_in($this->has['delete_role'], $this->get_user_roles());
			}
		}

		$this->ignore_delete_role = false;

		return $this;
	}

	/**
	 *
	 * Used by children classes to extend insert fields
	 *
	 * @access protected
	 *
	 * @param array $data
	 *
	 * @return Database_model
	 *
	 */
	protected function add_fields_on_insert(array &$data) : Database_model
	{
		if ($this->has['created_by']) {
			$data[$this->has['created_by']] = $this->get_user_id();
		}

		if ($this->has['created_on']) {
			$data[$this->has['created_on']] = $this->get_date_stamp();
		}

		if ($this->has['created_ip']) {
			$data[$this->has['created_ip']] = $this->get_ip();
		}

		$admin_role_id = config('auth.admin role id');

		if ($this->has['read_role']) {
			if (!isset($data[$this->has['read_role']])) {
				$data[$this->has['read_role']] = $this->get_user_read_role_id();
			}
		}

		if ($this->has['edit_role']) {
			if (!isset($data[$this->has['edit_role']])) {
				$data[$this->has['edit_role']] = $this->get_user_edit_role_id();
			}
		}

		if ($this->has['delete_role']) {
			if (!isset($data[$this->has['delete_role']])) {
				$data[$this->has['delete_role']] = $this->get_user_delete_role_id();
			}
		}

		if ($this->has['is_deleted']) {
			$data[$this->has['is_deleted']] = 0;
		}

		return $this;
	}

	/**
	 *
	 * Used by children classes to extend update fields
	 *
	 * @access protected
	 *
	 * @param array $data
	 *
	 * @return Database_model
	 *
	 */
	protected function add_fields_on_update(array &$data) : Database_model
	{
		if ($this->has['updated_by']) {
			$data[$this->has['updated_by']] = $this->get_user_id();
		}

		if ($this->has['updated_on']) {
			$data[$this->has['updated_on']] = $this->get_date_stamp();
		}

		if ($this->has['updated_ip']) {
			$data[$this->has['updated_ip']] = $this->get_ip();
		}

		return $this;
	}

	/**
	 *
	 * Used by children classes to extend delete fields
	 *
	 * @access protected
	 *
	 * @param array $data
	 *
	 * @return Database_model
	 *
	 */
	protected function add_fields_on_delete(array &$data) : Database_model
	{
		if ($this->has['deleted_by']) {
			$data[$this->has['deleted_by']] = $this->get_user_id();
		}

		if ($this->has['deleted_on']) {
			$data[$this->has['deleted_on']] = $this->get_date_stamp();
		}

		if ($this->has['deleted_ip']) {
			$data[$this->has['deleted_ip']] = $this->get_ip();
		}

		if ($this->has['is_deleted']) {
			$data[$this->has['is_deleted']] = 1;
		}

		return $this;
	}

	/**
	 *
	 * Used by children classes to extend select where clause
	 *
	 * @access protected
	 *
	 * @return Database_model
	 *
	 */
	protected function add_where_on_select() : Database_model
	{
		return $this->where_deleted()->where_can_read();
	}

	protected function where_deleted() : Database_model
	{
		if ($this->get_soft_delete()) {
			if (is_array($this->deleted_where_clause)) {
				$this->_database->where($this->deleted_where_clause);
			}
		}

		return $this;
	}

	/**
	 *
	 * Used by children classes to extend update where clause
	 *
	 * @access protected
	 *
	 * @param $data
	 *
	 * @return Database_model
	 *
	 */
	protected function add_where_on_update(array &$data) : Database_model
	{
		return $this->where_can_edit($data);
	}

	/**
	 *
	 * Used by children classes to extend insert where clause
	 *
	 * @access protected
	 *
	 * @param $data
	 *
	 * @return Database_model
	 *
	 */
	protected function add_where_on_insert(array &$data) : Database_model
	{
		return $this;
	}

	/**
	 *
	 * Used by children classes to extend delete where clause
	 *
	 * @access protected
	 *
	 * @param $data
	 *
	 * @return Database_model
	 *
	 */
	protected function add_where_on_delete(array &$data) : Database_model
	{
		return $this->where_can_delete($data);
	}

	/**
	 *
	 * Get the current user id
	 *
	 * @access protected
	 *
	 * @return int
	 *
	 */
	protected function get_user_id() : int
	{
		return (int)ci()->user->id;
	}

	/**
	 *
	 * Get the current user roles
	 * make sure something is returned since this is used in the where in clause
	 * if nothing was returned we still need the in clause or nothing would be filtered out
	 *
	 * @access protected
	 *
	 * @return array
	 *
	 */
	protected function get_user_roles() : array
	{
		$roles = ci()->user->roles();

		/* we must return something for the in clause or every record will match which is not what we want */
		$keys = [-1];

		if (is_array($roles)) {
			if (count($roles) > 0) {
				$keys = array_keys($roles);
			}
		}

		return $keys;
	}

	/**
	 *
	 * Get the users read role id
	 *
	 * @access protected
	 *
	 * @return int
	 *
	 */
	protected function get_user_read_role_id() : int
	{
		return (int)ci()->user->user_read_role_id;
	}

	/**
	 *
	 * Get the users edit role id
	 *
	 * @access protected
	 *
	 * @return int
	 *
	 */
	protected function get_user_edit_role_id() : int
	{
		return (int)ci()->user->user_edit_role_id;
	}

	/**
	 *
	 * Get the users delete role id
	 *
	 * @access protected
	 *
	 * @return int
	 *
	 */
	protected function get_user_delete_role_id() : int
	{
		return (int)ci()->user->user_delete_role_id;
	}

	/**
	 *
	 * Add any additional role rules
	 * because we still need to enforce model input regardless of where it's from
	 *
	 * @access protected
	 *
	 * @return Database_model
	 *
	 */
	protected function add_additional_rules() : Database_model
	{
		/**
		 * does this model have rules? if so add the role validation rules
		 */
		if ($this->has['read_role']) {
			$this->rules = $this->rules + [$this->has['read_role'] => ['field' => $this->has['read_role'], 'label' => 'Read Role', 	'rules' => 'integer|max_length[10]|less_than[4294967295]|filter_int[10]']];
		}

		if ($this->has['edit_role']) {
			$this->rules = $this->rules + [$this->has['edit_role'] => ['field' => $this->has['edit_role'], 'label' => 'Edit Role', 	'rules' => 'integer|max_length[10]|less_than[4294967295]|filter_int[10]']];
		}

		if ($this->has['delete_role']) {
			$this->rules = $this->rules + [$this->has['delete_role'] => ['field' => $this->has['delete_role'], 'label' => 'Delete Role', 'rules' => 'integer|max_length[10]|less_than[4294967295]|filter_int[10]']];
		}

		return $this;
	}

	/**
	 * Make sure each column is added to data even if empty
	 * this makes sure each validation rule can work on something if necessary
	 * if data didn't include the column then the rules would be skipped
	 *
	 * @access protected
	 *
	 * @param array $data fields to append columns to
	 * @param string $rule_set rule set name ie. update, insert, delete, form_login
	 *
	 * @return Database_model
	 *
	 */
	protected function add_rule_set_columns(array &$fields, string $rule_set) : Database_model
	{
		if (isset($this->rule_sets[$rule_set])) {
			foreach (explode(',', $this->rule_sets[$rule_set]) as $fieldname) {
				if (!isset($fields[$fieldname])) {
					/* not in the fields set so add it as empty */
					$fields[$fieldname] = '';
				}
			}
		}

		return $this;
	}

	/**
	 *
	 * if debug save the last query into a log file
	 * this is mostly for development and should really be used on a live system
	 * as this file can grow very quickly!
	 *
	 * @access protected
	 *
	 * @return Database_model
	 *
	 */
	protected function log_last_query() : Database_model
	{
		if ($this->debug) {
			$query  = $this->_database->last_query();
			$output = (is_array($query)) ? print_r($query, true) : $query;
			file_put_contents(LOGPATH.'/model.'.get_called_class().'.log', $output.chr(10), FILE_APPEND);
		}

		return $this;
	}

	/**
	 *
	 * Delete cache entries by tag
	 * This can be extended to provide additional features
	 *
	 * @access protected
	 *
	 * @return Database_model
	 *
	 */
	protected function delete_cache_by_tags() : Database_model
	{
		ci('cache')->delete_by_tags($this->cache_prefix);

		return $this;
	}

	/**
	 *
	 * Return the current date for time-stamping methods
	 *
	 * @access protected
	 *
	 * @param string $format [Y-m-d H:i:s]
	 *
	 * @return string
	 *
	 */
	protected function get_date_stamp(string $format='Y-m-d H:i:s') : string
	{
		/* also handles unit testing hard coded timestamp */
		$timestamp = (defined('PHPUNITTIMESTAMP')) ? PHPUNITTIMESTAMP : time();

		return date($format, $timestamp);
	}

	/**
	 *
	 * Return the current IP for time-stamping methods
	 *
	 * @access protected
	 *
	 * @return string
	 *
	 */
	protected function get_ip() : string
	{
		return ci('input')->ip_address();
	}
} /* end class */
