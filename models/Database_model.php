<?php
/**
 * Database_model
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
 */
class Database_model extends MY_Model {
	protected $db_group = null; /* database config to use for _database */
	protected $read_db_group = null; /* database config to use for reads */
	protected $write_db_group = null; /* database config to use for write */

	protected $table; /* database models table name */

	protected $protected = []; /* protected $data columns - these are never inserted / updated */
	protected $debug = false; /* boolean stored in ROOTPATH.'/var/logs/model.{class name}.log' */
	protected $primary_key = 'id'; /* primary id used as default for many SQL commands */
	protected $additional_cache_tags = ''; /* additional cache tags to add to cache prefix remember each tag is separated by . */
	protected $entity = null; /* true or string name of the entity to use for records - if true it uses the class name and replaces _model with _entity */
	protected $base_entity = null; /* empty entity */
	protected $has_roles = false; /* does this table use the standard role columns? these are automatically added to index, insert query's */
	protected $has_stamps = false; /* does this table use the standard timestamps columns? these are automatically added to insert, update, delete query's */
	protected $has_soft_delete = false; /* does this table support soft delete? */

	protected $cache_prefix; /* calculated in construct - internal */
	protected $auto_generated_primary = true; /* if the primary key is auto generated then remove it from insert commands */

	/*
	these can be set in the model using the constants ADMIN_ROLE_ID or NOBODY_USER_ID
	or by using set_role_ids($read_id=null,$edit_id=null,$delete_id=null)
	*/
	protected $read_role_id = null;
	protected $edit_role_id = null;
	protected $delete_role_id = null;

	/* internal */
	protected $_database; /* local instance of database connection */
	protected $read_database = null; /* local instance of write database connection */
	protected $write_database = null; /* local instance of read database connection */

	protected $temporary_with_deleted = false;
	protected $temporary_only_deleted = false;
	protected $temporary_column_name = null;
	protected $temporary_return_as_array = null;

	protected $default_return_on_many; /* return this on "no records found" */
	protected $default_return_on_single; /* return this on "no record found" */

/**
 * __construct
 */
	public function __construct() {
		/* setup MY_Model */
		parent::__construct();

		/* models aren't always database tables so set the object name to the table name */
		$this->object = strtolower($this->table);

		/* setup the cache prefix for this model so we can flush the cache based on tags */
		$this->cache_prefix = trim('database.'.$this->object.'.'.trim($this->additional_cache_tags,'.'),'.');

		/* is a database group attached other than the default? */
		$group_attach = false;

		/* is db group set? then that's the connection config we will use */
		if (isset($this->db_group)) {
			$this->_database = $this->load->database($this->db_group, true);

			$group_attach = true;
		}

		/* is read db group set? then that's the connection config we will use for reads */
		if (isset($this->read_db_group)) {
			$this->read_database = $this->load->database($this->read_db_group, true);
			$group_attach = true;
		}

		/* is write db group set? then that's the connection config we will use for writes */
		if (isset($this->write_db_group)) {
			$this->write_database = $this->load->database($this->write_db_group, true);
			$group_attach = true;
		}

		/* if a group isn't attached then user the default database connection */
		if (!$group_attach) {
			$this->_database = $this->db;
		}

		/* does this model have rules? if so add the role validation rules */
		if ($this->has_roles) {
			$this->rules = $this->rules + [
				'read_role_id' => ['field' => 'read_role_id', 'label' => 'Read Role', 	'rules' => 'required|integer|max_length[10]|less_than[4294967295]|filter_int[10]'],
				'edit_role_id' => ['field' => 'edit_role_id', 'label' => 'Edit Role', 	'rules' => 'required|integer|max_length[10]|less_than[4294967295]|filter_int[10]'],
				'delete_role_id' => ['field' => 'delete_role_id', 'label' => 'Delete Role', 'rules' => 'required|integer|max_length[10]|less_than[4294967295]|filter_int[10]'],
			];
		}

		/* what is the default on return many */
		$this->default_return_on_many = [];

		/* is there are record entity attached? */
		if ($this->entity) {
			/*
			yes try to figure out the entity

			if the value is "true" then use the model name minus "model" and replace with "entity"
			else use the string stored in entity
			*/
			$this->entity = ($this->entity === true) ? ucfirst(strtolower(substr(get_class($this),0,-5)).'entity') : $this->entity;

			ci('load')->entity($this->entity,$this->base_entity);

			$this->default_return_on_single =& $this->base_entity;
		} else {
			/* on single record return a class */
			$this->default_return_on_single = new stdClass();
		}

		log_message('info', 'Database_model Class Initialized');
	}

/**
 * Try to call built in CodeIgniter Database methods on the CodeIgniter _database object
 *
 * @param $name string
 * @param $arguments mixed
 *
 * @return $this
 *
 */
	public function __call($name, $arguments) {
		if (method_exists($this->_database,$name)) {
			call_user_func_array([$this->_database,$name],$arguments);
		}

		return $this;
	}

/**
 * set the default role id for insert actions where it's not supplied in $data
 *
 * @param $read_id integer
 * @param $edit_id integer
 * @param $delete_id integer
 *
 * @return $this
 *
 */
	public function set_role_ids($read_id=null,$edit_id=null,$delete_id=null) {
		if ($read_id) {
			$this->read_role_id = (int)$read_id;
		}

		if ($edit_id) {
			$this->edit_role_id = (int)$edit_id;
		}

		if ($delete_id) {
			$this->delete_role_id = (int)$delete_id;
		}

		return $this;
	}

/**
 * Return the current cache prefix
 *
 * @return string
 *
 */
	public function get_cache_prefix() {
		return (string)$this->cache_prefix;
	}

/**
 * Return the current table name
 *
 * @return string
 *
 */
	public function get_tablename() {
		return (string)$this->table;
	}

/**
 * Return the current primary key
 *
 * @return
 *
 */
	public function get_primary_key() {
		return (string)$this->primary_key;
	}

/**
 * Return if this table uses soft deletes
 *
 * @return boolean
 *
 */
	public function get_soft_delete() {
		return (bool)$this->has_soft_delete;
	}

/**
 * Return the current query as an array overriding what ever is currently set as default
 *
 * @return $this
 *
 */
	public function as_array() {
		$this->temporary_return_as_array = true;

		return $this;
	}

/**
 * Return only this single column
 *
 * @param $name string
 *
 * @return
 *
 */
	public function column($name) {
		$this->temporary_column_name = (string)$name;

		return $this;
	}

/**
 * If no records / record are found return this
 *
 * @param $return mixed
 *
 * @return $this
 *
 */
	public function on_empty_return($return) {
		$this->default_return_on_single	= $return;
		$this->default_return_on_many	= $return;

		return $this;
	}

/**
 * Get a single record
 *
 * @param $primary_value
 *
 * @return mixed
 *
 */
	public function get($primary_value = null) {
		return ($primary_value === null) ? $this->default_return_on_single : $this->get_by([$this->primary_key => $primary_value]);
	}

/**
 * Get a single using a where clause
 *
 * @param $where array
 *
 * @return mixed
 *
 */
	public function get_by($where = null) {
		if ($where) {
			$this->_database->where($where);
		}

		return $this->_get(false);
	}

/**
 * Get multiple records
 *
 * @return mixed
 *
 */
	public function get_many() {
		return $this->get_many_by();
	}

/**
 * Get multiple records using a where clause
 *
 * @param $where array
 *
 * @return mixed
 *
 */
	public function get_many_by($where = null) {
		if ($where) {
			$this->_database->where($where);
		}

		return $this->_get(true);
	}

/**
 * Preform the actual SQL select
 *
 * @param $as_array boolean return as a array
 * @param $table string optional
 *
 * @return mixed
 *
 */
	protected function _get($as_array = true, $table = null) {
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

		/* run the actual CodeIgniter query builder select */
		$dbc = $this->_database->get($table);

		/* log it if we need to */
		$this->log_last_query();

		/* what type of results are they looking for? */
		$results = ($as_array) ? $this->_as_array($dbc) : $this->_as_row($dbc);

		/* ar they looking for a single column? */
		if ($this->temporary_column_name) {
			/* yes - then the results are that single column */

			/* is it a array or object that was returned */
			if (is_array($results)) {
				$results = $results[$this->temporary_column_name];
			} elseif (is_object($results)) {
				$results = $results->{$this->temporary_column_name};
			}
		}

		/* clear the temp stuff */
		$this->_clear();

		/* return the results */
		return $results;
	}

/**
 * Clear our query temp values etc...
 *
 * @return $this
 *
 */
	public function _clear() {
		$this->temporary_column_name = null;
		$this->temporary_return_as_array = null;
		$this->default_return_on_many = [];
		$this->default_return_on_single = ($this->base_entity) ? $this->base_entity : new stdClass();

		return $this;
	}

/**
 * Insert a database record based on the name value associated array pairs
 *
 * @param $data array
 *
 * @return mixed - false on fail or the insert id
 *
 */
	public function insert($data) {
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
		$success = (!$this->skip_rules && count($this->rules)) ? $this->only_columns($data,$this->rules)->add_rule_set_columns($data,'insert')->validate($data) : true;

		/* if the validation was successful then proceed */
		if ($success) {
			/*
			remap any data field columns to actual data base columns
			remove the protected columns
			call the add field on insert method which can be overridden on the extended class
			call the add where on insert method which can be overridden on the extended class
			*/
			$this->remap_columns($data, $this->rules)->remove_columns($data, $this->protected)->add_fields_on_insert($data)->add_where_on_insert($data);

			/* are there any columns left? */
			if (count($data)) {
				/* yes - run the actual CodeIgniter Database insert */
				$this->_database->insert($this->table, $data);
			}

			/* ok now delete any caches since we did a insert and log it if we need to */
			$this->delete_cache_by_tags()->log_last_query();

			/*
			set success to the insert id - if there is no auto generated primary if 0 is
			returned so exact (===) should be used on the results to determine if it's "really" a error (false)
			*/
			$success = (int) $this->_database->insert_id();
		}

		/* clear the temp stuff */
		$this->_clear();

		/* return false on error or the primary id of the auto generated primary if if there is no auto generated primary if 0 is returned */
		return $success;
	}

/**
 * Make sure each column is added to data even if empty
 * this makes sure each validation rule can work on something if necessary
 * if data didn't include the column then the rules would be skipped
 *
 * @param $data
 * @param $which_set
 *
 * @return $this
 *
 */
	protected function add_rule_set_columns(&$data,$which_set) {
		if (isset($this->rule_sets[$which_set])) {

			$required_fields = explode(',',$this->rule_sets[$which_set]);

			foreach ($required_fields as $required_field) {
				if (!isset($data[$required_field])) {
					$data[$required_field] = '';
				}
			}
		}

		return $this;
	}

/**
 * Update a database record based on the name value associated array pairs
 *
 * @param $data array
 *
 * @return mixed - false on fail or the affected rows
 *
 */
	public function update($data) {
		/* convert the input to any array if it's not already */
		$data = (array)$data;

		/* the primary key must be set to use this command */
		if (!isset($data[$this->primary_key])) {
			/* if not than throw error */
			throw new Exception('Database Model update primary key missing');
		}

		/* call by using the primary key */
		return $this->update_by($data, [$this->primary_key => $data[$this->primary_key]]);
	}

/**
 * Update a database record based on the name value associated array pairs using a where clause
 *
 * @param $data array
 * @param $where array
 *
 * @return mixed - false on fail or the affected rows
 *
 */
	public function update_by($data, $where = []) {
		/* switch to the write database if we are using 2 different connections */
		$this->switch_database('write');

		/* convert the input to any array if it's not already */
		$data = (array)$data;

		/* preform the validation if there are rules and skip rules is false only using the data input that has rules using the update rule set */
		$success = (!$this->skip_rules && count($this->rules)) ? $this->only_columns($data,$this->rules)->add_rule_set_columns($data,'update')->validate($data) : true;

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
			$this->remap_columns($data, $this->rules)->remove_columns($data, $this->protected)->add_fields_on_update($data)->add_where_on_update($data);

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
		$this->_clear();

		/* return false on error or 0 (also false) if no rows changed */
		return $success;
	}

/**
 * Delete based on primary key
 *
 * @param $arg
 *
 * @return mixed - false on fail or the affected rows
 *
 */
	public function delete($arg) {
		return $this->delete_by($this->create_where($arg,true));
	}

/**
 * delete_by
 * Insert description here
 *
 * @param $data
 *
 * @return mixed - false on fail or the affected rows
 *
 */
	public function delete_by($data) {
		/* switch to the write database if we are using 2 different connections */
		$this->switch_database('write');

		/* convert the input to any array if it's not already */
		$data = (array)$data;

		/* preform the validation if there are rules and skip rules is false only using the data input that has rules using the delete rule set */
		$success = (!$this->skip_rules && count($this->rules)) ? $this->only_columns($data,$this->rules)->add_rule_set_columns($data,'delete')->validate($data) : true;

		/* if the validation was successful then proceed */
		if ($success) {
			/* remap any data field columns to actual data base columns */
			$this->remap_columns($data, $this->rules);

			/* does this model support soft delete */
			if ($this->has_soft_delete) {
				/* save a copy of data */
				$where = $data;

				/* add is_delete column to data passed in */
				$data = $data + ['is_deleted'=>1];

				/* call the add field on delete method which can be overridden on the extended class */
				$this->add_fields_on_delete($data);

				/* preform the actual CodeIgniter Database soft delete */
				$this->_database->where($where)->set($data)->update($this->table);
			} else {
				/* preform the actual CodeIgniter Database Delete */
				$this->_database->where($data)->delete($this->table);
			}

			/* ok now delete any caches since we did a delete and log it if we need to */
			$this->delete_cache_by_tags()->log_last_query();

			/* set success to the affected rows returned */
			$success = (int) $this->_database->affected_rows();
		}

		/* clear the temp stuff */
		$this->_clear();

		/* return false on error or 0 (also false) if no rows changed */
		return $success;
	}

/**
 * Convert Database Cursor into something useable
 *
 * @param $dbc database cursor object
 *
 * @return mixed
 *
 */
	protected function _as_array($dbc) {
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
 * Convert Database Cursor into a useable record
 *
 * @param $dbc database cursor object
 *
 * @return $mixed
 *
 */
	protected function _as_row($dbc) {
		/* setup default if empty */
		$result = $this->default_return_on_single;

		/* is the cursor actually a object? */
		if (is_object($dbc)) {
			/* 1 or more rows found? */
			if ($dbc->num_rows()) {
				/* yes - ok let's return a entity, array, or object */
				if ($this->entity && $this->temporary_return_as_array !== true) {
					$result = $dbc->custom_row_object(0, $this->entity);
				} elseif($this->temporary_return_as_array)  {
					$result = $dbc->row_array();
				} else {
					$result = $dbc->row();
				}
			}
		}

		return $result;
	}

/**
 * if debug save the last query into a log file
 * this is mostly for development and should really be used on a live system
 * as this file can grow very quickly!
 *
 * @return $this
 *
 */
	protected function log_last_query() {
		if ($this->debug) {
			$query  = $this->_database->last_query();
			$output = (is_array($query)) ? print_r($query, true) : $query;
			file_put_contents(LOGPATH.'/model.'.get_called_class().'.log',$output.chr(10), FILE_APPEND);
		}

		return $this;
	}

/**
 * Delete cache entries by tag
 * This can be extended to provide additional features
 *
 * @return $this
 *
 */
	protected function delete_cache_by_tags() {
		ci('cache')->delete_by_tags($this->cache_prefix);

		return $this;
	}

/**
 * Catalog provides a simple way and interface to make a simple query
 *
 * @param $array_key
 * @param $select_columns
 * @param $where
 * @param $order_by
 *
 * @return array
 *
	ci('status_model')->catalog()
		array (
			1 =>
			Status_entity::__set_state(array(
				'id' => '1',
				'human' => 'Error',
				'color' => 'ff0000',
				'icon' => 'asterisk',

	ci('status_model')->catalog('human','*')
		array (
			'Error' => 'ff0000',
			'Ok' => '38cfbd',

	ci('status_model')->catalog('human','color')
		array (
			'Error' =>
			Status_entity::__set_state(array(
				'id' => '1',
				'human' => 'Error',
				'color' => 'ff0000',
				'icon' => 'asterisk',

	ci('status_model')->catalog('human','*',['is_deleted'=>0])
		array (
			'Error' =>
			Status_entity::__set_state(array(
				'id' => '1',
				'human' => 'Error',
				'color' => 'ff0000',
				'icon' => 'asterisk',

	ci('status_model')->catalog('human','*',['is_deleted'=>0],'name') defaults to asc
	ci('status_model')->catalog('human','*',['is_deleted'=>0],'name desc')
		array (
			'SkyBlue' =>
			Status_entity::__set_state(array(
				'id' => '3',
				'human' => 'SkyBlue',
				'color' => '215eb8',
				'icon' => 'dribbble',

 *
 */
	public function catalog($array_key = null, $select_columns = null, $where = null, $order_by = null, $cache_key = null, $with_deleted = false) {
		/* setup the default return value */
		$is_cached = false;

		if (is_string($cache_key)) {
			$results = ci('cache')->get($this->cache_prefix.'.'.$cache_key);

			if (is_array($results)) {
				$is_cached = true;
			}
		}

		if (!$is_cached) {
			$results = [];

			if ($with_deleted) {
				$this->temporary_with_deleted = true;
			}

			/* we aren't looking for a single column by default */
			$single_column = false;

			/* if array_key is empty then use the primary key */
			$array_key = ($array_key) ? $array_key : $this->primary_key;

			/* are the select columns a comma sep. array or array already? */
			$select_columns = is_array($select_columns) ? implode(',',$select_columns) : $select_columns;

			/* if select columns is null or * (all) then select is all */
			if ($select_columns === null || $select_columns == '*') {
				$select = '*';
			} else {
				/* format the select to a comma sep list and add array key if needed */
				$select = $array_key.','.$select_columns;
				if (strpos($select_columns,',') === false) {
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
				if (strpos($order_by,' ') === false) {
					$this->_database->order_by($order_by);
				} else {
					list($column,$direction) = explode(' ',$order_by,2);

					$this->_database->order_by($column,$direction);
				}
			}

			/* run the actual query */
			$dbc = $this->_get(true);

			/* for each returned row format into a simple array with keys and values or complex with keys and array of columns */
			foreach ($dbc as $dbr) {
				if ($single_column) {
					$results[$dbr->$array_key] = $dbr->$single_column;
				} else {
					$results[$dbr->$array_key] = $dbr;
				}
			}

			if ($cache_key) {
				ci('cache')->save($this->cache_prefix.'.'.$cache_key,$results);
			}

		}

		return $results;
	}

/**
 * is unique model based
 *
 * @param $field string - value we are testing
 * @param $column string - database column name
 * @param $form_key string - form input key
 *
 * @return
 *
 * $success = ci('foo_model')->is_uniquem('Johnny Appleseed','name','id');
 *
 */
	public function is_uniquem($field, $column, $form_key) {
		/* run the query return a maximum of 3 */
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
 * update_if_exists
 * Insert description here
 *
 * @param $data
 * @param $where
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function update_if_exists($data,$where=false) {
		$where = ($where) ? $where : [$this->primary_key=>$data[$this->primary_key]];
		$record = $this->exists($where);

		return (isset($record->{$this->primary_key})) ? $this->update_by($data,[$this->primary_key=>$record->{$this->primary_key}]) : $this->insert($data);
	}

/**
 * exists
 * Insert description here
 *
 * @param $arg
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function exists($arg) {
		/* did we get one or more columns */
		return $this->on_empty_return(false)->get_by($this->create_where($arg));
	}

/**
 * count
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
	public function count() {
		return $this->count_by();
	}

/**
 * count_by
 * Insert description here
 *
 * @param $where
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function count_by($where = null) {
		$this->_database->select("count('".$this->primary_key."') as codeigniter_column_count");

		if ($where) {
			$this->_database->where($where);
		}

		$results = $this->_get(false);

		return (int)$results->codeigniter_column_count;
	}

/**
 * index
 * Insert description here
 *
 * @param $order_by
 * @param $limit
 * @param $where
 * @param $select
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function index($order_by = null, $limit = null, $where = null, $select = null) {
		if ($order_by) {
			$this->_database->order_by($order_by);
		}

		if ($limit) {
			$this->_database->limit($limit);
		}

		if ($select) {
			$this->_database->select($select);
		}

		if ($where) {
			$this->_database->where($where);
		}

		if ($this->has_roles) {
			$this->where_can_read();
		}

		return $this->_get(true);
	}

/**
 * switch_database
 * Insert description here
 *
 * @param $which
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	protected function switch_database($which) {
		if ($which == 'read' && $this->read_database) {
			$this->_database = $this->read_database;
		} elseif ($which == 'write' && $this->write_database) {
			$this->_database = $this->write_database;
		}

		return $this;
	}

/**
 * with_deleted
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
	public function with_deleted() {
		$this->temporary_with_deleted = true;

		return $this;
	}

/**
 * only_deleted
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
	public function only_deleted() {
		$this->temporary_only_deleted = true;

		return $this;
	}

/**
 * restore
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
	public function restore($id) {
		$data['is_deleted'] = 0;

		$this->_add_fields_on_update($data);
		$this->_database->update($this->table, $data, $this->create_where($id,true));
		$this->delete_cache_by_tags()->log_last_query();

		return (int) $this->_database->affected_rows();
	}

/**
 * create_where
 * Insert description here
 *
 * @param $arg
 * @param $primary_id_required
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	protected function create_where($arg,$primary_id_required=false) {
		if (is_scalar($arg)) {
			$where = [$this->primary_key=>$arg];
		} elseif (is_array($arg)) {
			$where = $arg;
		} else {
			throw new Exception('Unable to determine where clause in "'.__CLASS__.'"');
		}

		if ($primary_id_required) {
			if (!isset($where[$this->primary_key])) {
				throw new Exception('Unable to determine primary id where clause in "'.__CLASS__.'"');
			}
		}

		return $where;
	}

/**
 * where_can_read
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
	protected function where_can_read() {
		$this->_database->where_in('read_role_id',array_keys(ci('user')->roles()));

		return $this;
	}

/**
 * where_can_edit
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
	protected function where_can_edit() {
		$this->_database->where_in('edit_role_id',array_keys(ci('user')->roles()));

		return $this;
	}

/**
 * where_can_delete
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
	protected function where_can_delete() {
		$this->_database->where_in('delete_role_id',array_keys(ci('user')->roles()));

		return $this;
	}

/**
 * _get_userid
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
	protected function _get_userid() {
		$user_id = NOBODY_USER_ID;

		if (is_object(ci()->user)) {
			if (ci()->user->id) {
				$user_id = ci()->user->id;
			}
		}

		return $user_id;
	}

/**
 * add_fields_on_insert
 * Insert description here
 *
 * @param $data
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	protected function add_fields_on_insert(&$data) {
		if ($this->has_stamps) {
			$data['created_by'] = $this->_get_userid();
			$data['created_on'] = date('Y-m-d H:i:s');
			$data['created_ip'] = ci()->input->ip_address();
		}

		if ($this->has_roles) {
			$read_role_id = max((int)$this->read_role_id,(int)ci('user')->user_read_role_id);
			$edit_role_id = max((int)$this->edit_role_id,(int)ci('user')->user_edit_role_id);
			$delete_role_id = max((int)$this->delete_role_id,(int)ci('user')->user_delete_role_id);
			$admin_role_id = config('auth.admin role id');

			if (!isset($data['read_role_id'])) {
				$data['read_role_id'] = ($read_role_id > 0) ? $read_role_id : $admin_role_id;
			}

			if (!isset($data['edit_role_id'])) {
				$data['edit_role_id'] = ($edit_role_id > 0) ? $edit_role_id : $admin_role_id;
			}

			if (!isset($data['delete_role_id'])) {
				$data['delete_role_id'] = ($delete_role_id > 0) ? $delete_role_id : $admin_role_id;
			}
		}

		return $this;
	}

/**
 * add_fields_on_update
 * Insert description here
 *
 * @param $data
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	protected function add_fields_on_update(&$data) {
		if ($this->has_stamps) {
			$data['updated_by'] = $this->_get_userid();
			$data['updated_on'] = date('Y-m-d H:i:s');
			$data['updated_ip'] = ci()->input->ip_address();
		}

		return $this;
	}

/**
 * add_fields_on_delete
 * Insert description here
 *
 * @param $data
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	protected function add_fields_on_delete(&$data) {
		if ($this->has_soft_delete && $this->has_stamps) {
			$data['deleted_by'] = $this->_get_userid();
			$data['deleted_on'] = date('Y-m-d H:i:s');
			$data['deleted_ip'] = ci()->input->ip_address();
		}
		return $this;
	}

/**
 * add_where_on_select
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
	protected function add_where_on_select() {
		if ($this->has_soft_delete) {
			if ($this->temporary_with_deleted !== true) {
				$this->_database->where('is_deleted', (($this->temporary_only_deleted) ? 1 : 0));
			}
		}
		return $this;
	}

/**
 * add_where_on_update
 * Insert description here
 *
 * @param $data
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	protected function add_where_on_update(&$data) {
		return $this;
	}

/**
 * add_where_on_insert
 * Insert description here
 *
 * @param $data
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	protected function add_where_on_insert(&$data) {
		return $this;
	}

}
