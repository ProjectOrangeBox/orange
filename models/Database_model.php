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
	protected $db_group = null; /* database config group to use */

	protected $read_db_group = null; /* read only database group */
	protected $write_db_group = null; /* write database group */
	
	protected $table; /* table name - this is also used as the resource object name */
	protected $protected = []; /* protect these columns from auto set */
	
	protected $debug = false; /* path to debug file to write - used naturally for local debugging */

	/* This model's default primary key or unique identifier. Used by the get(),update() and delete() functions. */
	protected $primary_key = 'id';
	
	protected $additional_cache_tags = ''; /* example 'tag1.tag2.tag' */
	
	protected $skip_rules = false; /* wether to skip all rule validation (probably because you don't have rules) */
	
	protected $entity = null;

	protected $has_roles = false;
	protected $has_stamps = false;
	protected $has_soft_delete = false;

	/* Internal */
	protected $default_return_on_many;
	protected $default_return_on_single;

	protected $read_database = null; /* read only database resource */
	protected $write_database = null; /* write database resource */

	/**
	* The database connection object. Will be set to the default
	* connection. This allows individual models to use different DBs
	* without overwriting CI's global $this->db connection.
	*/
	protected $_database;
	protected $cache_prefix; /* this is auto generated in the constructor */

	protected $temporary_with_deleted = false;
	protected $temporary_only_deleted = false;
	protected $temporary_return_on_single = null;
	protected $temporary_return_on_many = null;
	protected $temporary_column_name = null;
	protected $temporary_return_as_array = null;
	
	/* Initialize the model, tie into the CodeIgniter super-object */
	public function __construct() {
		parent::__construct();
		
		/* the parent model expects a more generic object "name" */
		$this->object = strtolower($this->table);

		$this->cache_prefix = trim('database.' . $this->object . '.' . trim($this->additional_cache_tags,'.'),'.');

		/* use a custom database connection or default? */
		$group_attach = false;
		
		if (isset($this->db_group)) {
			$this->_database = $this->load->database($this->db_group, true);

			$group_attach = true;
		}

		if (isset($this->read_db_group)) {
			$this->read_database = $this->load->database($this->read_db_group, true);

			$group_attach = true;
		}
	
		if (isset($this->write_db_group)) {
			$this->write_database = $this->load->database($this->write_db_group, true);

			$group_attach = true;
		}

		if (!$group_attach) {
			$this->_database = $this->db;
		}

		if ($this->has_roles) {
			$this->rules = $this->rules + [
				'read_role_id'   => ['field' => 'read_role_id', 	'label' => 'Read Role', 	'rules' => 'required|integer|max_length[10]|less_than[4294967295]|filter_int[10]'],
				'edit_role_id'   => ['field' => 'edit_role_id', 	'label' => 'Edit Role', 	'rules' => 'required|integer|max_length[10]|less_than[4294967295]|filter_int[10]'],
				'delete_role_id' => ['field' => 'delete_role_id', 'label' => 'Delete Role', 'rules' => 'required|integer|max_length[10]|less_than[4294967295]|filter_int[10]'],
			];
		}

		if ($this->entity) {
			$this->entity = ($this->entity === true) ? ucfirst(strtolower(substr(get_class($this),0,-5)).'entity') : $this->entity;
			
			/* load root the entity this does not need to be attached to the CI super global */
			require_once 'models/Model_entity.php';
	
			/* load supplied entity */
			require_once 'models/entities/'.$this->entity.'.php';
	
			/* override the models */
			$this->default_return_on_many = [];
			$this->default_return_on_single = new $this->entity();
		} else {
			$this->default_return_on_many = [];
			$this->default_return_on_single = new stdClass();
		}

		log_message('info', 'Database_model Class Initialized');
	}
	
	/* adding this make "most" other database methods work */
	public function __call($name, $arguments) {
		if (method_exists($this->_database,$name)) {
			call_user_func_array([$this->_database,$name],$arguments);
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

	public function get_soft_delete() {
		return $this->has_soft_delete;
	}
	
	public function set_temp_return_on_single($value) {
		$this->temporary_return_on_single = $value;
	
		return $this;
	}
	
	public function set_temp_return_on_many($value) {
		$this->temporary_return_on_many = $value;
	
		return $this;
	}
	
	public function set_temp_return_as_array() {
		$this->temporary_return_as_array = true;
		
		return $this;
	}

	public function column($name) {
		$this->temporary_column_name = $name;
		
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

		return $this->_get(true);
	}

	protected function _get($as_array = true, $table = null) {
		$this->switch_database('read');

		$table = ($table) ? $table : $this->table;

		$dbc = $this->_database->get($table);

		$this->log_last_query();

		$results = ($as_array) ? $this->_as_array($dbc) : $this->_as_row($dbc);

		/* is this a single column we are looking for? */
		if ($this->temporary_column_name) {
			if (is_array($results)) {
				$results = $results[$this->temporary_column_name];
			} elseif (is_object($results)) {
				$results = $results->{$this->temporary_column_name};
			}
		}
		
		/* clean up after everything */
		$this->_clear();
		
		return $results;
	}

	public function _clear() {
		/* clear this out to not interfere with the next query */
		$this->temporary_column_name = null;
		$this->temporary_return_on_single = null;
		$this->temporary_return_on_many = null;
		$this->temporary_return_as_array = null;
		
		return $this;
	}

	public function insert($data) {
		$this->switch_database('write');

		$data = (array)$data;
		
		/* if the primary id is empty then set it to something random now and clear it later */
		if (empty($data[$this->primary_key])) {
			$data[$this->primary_key] = '-1';
		}
		
		/* strip columns that don't have rules */
		$this->only_columns_with_rules($data);

		/* make sure we add the rules for insert to enforce those rules incase they don't include them in the input array ($data) */
		if (isset($this->rule_sets['insert'])) {
			$required_insert_fields = explode(',',$this->rule_sets['insert']);
			
			foreach ($required_insert_fields as $required_insert_field) {
				if (!isset($data[$required_insert_field])) {
					$data[$required_insert_field] = ''; /* they are now part of the data array but empty */
				}
			}
		}
		
		$success = (!$this->skip_rules) ? $this->validate($data) : true;
		
		/* is it still the temp primary key? if yes remove it */
		if ($data[$this->primary_key] == '-1') {
			unset($data[$this->primary_key]);
		}

		if ($success) {
			/* remove the protected columns */
			$this->remove_columns($data, $this->protected);

			$this->add_fields_on_insert($data)->add_where_on_insert($data);

			if (count($data)) {
				$this->_database->insert($this->table, $data);
			}

			/* delete cache data with same cache prefix as this object */
			$this->delete_cache_by_tags()->log_last_query();

			$success = (int) $this->_database->insert_id();
		}

		$this->_clear();

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
		$this->switch_database('write');

		$data = (array)$data;

		/* strip columns that don't have rules */
		$this->only_columns_with_rules($data);
		
		/* make sure we add the rules for update to enforce those rules incase they don't include them in the input array ($data) */
		if (isset($this->rule_sets['update'])) {
			$required_insert_fields = explode(',',$this->rule_sets['update']);
			
			foreach ($required_insert_fields as $required_insert_field) {
				if (!isset($data[$required_insert_field])) {
					$data[$required_insert_field] = ''; /* they are now part of the data array but empty */
				}
			}
		}
		
		$success = (!$this->skip_rules) ? $this->validate($data) : true;

		/*
		unset the primary key field because we are either
		using that or don't want a mass update on the
		primary which will cause a error because it should be unique
		*/
		unset($data[$this->primary_key]);

		if ($success) {
			/* remove the protected columns */
			$this->remove_columns($data, $this->protected);

			$this->add_fields_on_update($data)->add_where_on_update($data);
			
			if (count($data)) {
				$this->_database->where($where)->update($this->table, $data);
			}

			/* delete cache data with same cache prefix as this object */
			$this->delete_cache_by_tags()->log_last_query();

			$success = (int) $this->_database->affected_rows();
		}

		$this->_clear();

		return $success;
	}

	public function delete($arg) {
		return $this->delete_by($this->create_where($arg,true));
	}

	public function delete_by($data) {
		$this->switch_database('write');

		$data = (array)$data;

		$success = (!$this->skip_rules) ? $this->only_columns_with_rules($data)->validate($data) : true;

		if ($success) {
			if ($this->has_soft_delete) {
				/* save the name/value pairs as the where clause */
				$where = $data;
	
				$data = $data + ['is_deleted'=>1];
	
				$this->add_fields_on_delete($data);
	
				$this->_database->where($where)->set($data)->update($this->table);
			} else {
				$this->_database->where($data)->delete($this->table);
			}
			
			$this->delete_cache_by_tags()->log_last_query();

			$success = (int) $this->_database->affected_rows();
		}

		$this->_clear();

		return $success;
	}
	
	protected function _as_array($dbc) {
		/* if database cursor is empty return by default what? */
		$result = ($this->temporary_return_on_many) ? $this->temporary_return_on_many : $this->default_return_on_many;

		/* multiple records */
		if (is_object($dbc)) {
			if ($dbc->num_rows()) {
				/* ok database cursor is valid what type of results set do we need */
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
	
	protected function _as_row($dbc) {
		/* if database cursor is empty return by default what? */
		$result = ($this->temporary_return_on_single) ? $this->temporary_return_on_single : $this->default_return_on_single;
	
		if (is_object($dbc)) {
			if ($dbc->num_rows()) {
				/* ok database cursor is valid what type of results set do we need */
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

	protected function log_last_query() {
		if ($this->debug) {
			$query  = $this->_database->last_query();
			$output = (is_array($query)) ? print_r($query, true) : $query;
			file_put_contents(ROOTPATH.'/var/logs/model.'.get_called_class().'.log',$output.chr(10), FILE_APPEND);
		}
		
		return $this;
	}

	protected function delete_cache_by_tags() {
		delete_cache_by_tags($this->cache_prefix);
		
		return $this;
	}

	/**
	 * Create a associated array
	 * this can be used for drop-downs or easy access to model values based on the primary ID for example
	 *
	 * if select is a single column name the created array will a simple name & value associated array pair
	 *
	 * The order by and where clause must follow the CodeIgniter function syntax
	 *
	 * where: https://www.codeigniter.com/user_guide/database/query_builder.html#looking-for-specific-data
	 * order by: https://www.codeigniter.com/user_guide/database/query_builder.html#ordering-results
	 *
	 * catalog = [
	 *  'bar_catalog'=>'bar_model', = select * from bar_model_table where the primary id is the array key and array value is the record
	 *  'foo_catalog'=>['array_key'=>'id{defaults to primary id}','select'=>'id,color{defaults to *}','where'=>['is_deleted'=>0]{defaults to none},'order_by'=>'color [asc|desc]'{defaults to none}],
	 * ]
	 * 
	 * catalog('id','name'); simple associated array key->value pair
	 * catalog('id','name,foo,bar'); associated array key->array pair
   *
	 * @author Don Myers
	 * @param	 string [$array_key = null] The returned array key value if empty this will use the primary key
	 * @param	 string [$select = null] The value to use for the array value if empty this will use the entire record
	 * @param	 [[Type]] [$where = null]			[[Description]]
	 * @param	 [[Type]] [$order_by = null]	[[Description]]
	 * @return [[Type]] [[Description]]
	 */
	public function catalog($array_key = null, $select_columns = null, $where = null, $order_by = null) {
		$results = [];

		/* is this a single column associated array? */
		$single_column = false;
		
		/* what's the primary key? */
		$array_key = ($array_key) ? $array_key : $this->primary_key;

		$select_columns = is_array($select_columns) ? implode(',',$select_columns) : $select_columns;

		/* what columns are they looking for? */
		if ($select_columns === null || $select_columns == '*') {
			$select = '*';
		} else {
			$select = $array_key . ',' . $select_columns;
			
			if (strpos($select_columns,',') === false) {
				$single_column = $select_columns;
			}
		}
		
		/* add them */
		$this->_database->select($select);
		
		/* did they include a where clause? */
		if ($where) {
			/* add it */
			$this->_database->where($where);
		}
		
		/* did they include a order by clause? */
		if ($order_by) {
			if (strpos($order_by,' ') === false) {
				$this->_database->order_by($order_by);
			} else {
				list($order_by,$direction) = explode($order_by,' ',2);

				$this->_database->order_by($order_by,$direction);
			}
		}
		
		/* run the query */
		$dbc = $this->_get(true); /* return multiple */

		foreach ($dbc as $dbr) {
			if ($single_column) {
				/* if it's single column it's a key->value pair */
				$results[$dbr->$array_key] = $dbr->$single_column;
			} else {
				/* if it's not then it's a key -> record pair */
				$results[$dbr->$array_key] = $dbr;
			}
		}

		/* return out results */
		return $results;
	}

	/*
	used by form validation to find unique
	
	is_uniquem[model.column.post_key]
	is_uniquem[o_user_model.email.id]
	
	*/
	public function is_uniquem($field, $column, $form_key) {
		$dbc = $this->_database
			->select($column . ',' . $this->primary_key)
			->where($column, $field)
			->get($this->table, 3); /* more than 1 but less than 4 */

		/* how many did we find? */
		$rows_found = $dbc->num_rows();

		if ($rows_found == 0) {
			/* nothing else named this exists */
			return true;
		}

		if ($rows_found > 1) {
			/* we found more than 1 so that is for sure a error! */
			return false;
		}

		/* does id on the record match the current record from a previous save? */
		return ($dbc->row()->{$this->primary_key} == $this->input->request($form_key));
	}

	/* boolean full text search column */
	public function build_sql_boolean_match($column_name, $match = null, $not_match = null) {
		$sql = false;
		$match_where = '';

		if (is_array($match) > 0) {
			$match_where .= ' +' . implode(' +', $match);
		}

		if (is_array($not_match) > 0) {
			$match_where .= ' -' . implode(' -', $not_match);
		}

		if (!empty($match_where)) {
			$sql = "match(`" . $column_name . "`) against('" . trim($match_where) . "' in boolean mode)";
		}

		return $sql; /* one exit */
	}

	public function update_if_exists($data,$where=false) {
		$where = ($where) ? $where : [$this->primary_key=>$data[$this->primary_key]];
	
		$record = $this->exists($where);

		return (isset($record->{$this->primary_key})) ? $this->update_by($data,[$this->primary_key=>$record->{$this->primary_key}]) : $this->insert($data);
	}

	public function exists($arg) {
		$record = $this->get_by($this->create_where($arg));
		
		return (isset($record->{$this->primary_key})) ? $record : false;
	}

	public function count() {
		return $this->count_by();
	}

	public function count_by($where = null) {
		$this->_database->select("count('" . $this->primary_key . "') as codeigniter_column_count");

		if ($where) {
			$this->_database->where($where);
		}

		$results = $this->_get(false);

		return (int)$results->codeigniter_column_count;
	}

	/*
	 * default method called to produce the index view records
	 *
	 *
	 * The order by, limit, where clause, and select must follow the CodeIgniter function syntax
	 *
	 * order by: https://www.codeigniter.com/user_guide/database/query_builder.html#ordering-results
	 * limit: https://www.codeigniter.com/user_guide/database/query_builder.html#limiting-or-counting-results
	 * where: https://www.codeigniter.com/user_guide/database/query_builder.html#looking-for-specific-data
	 * select: https://www.codeigniter.com/user_guide/database/query_builder.html#selecting-data
	 *
	 * @author Don Myers
	 * @param	 [[Type]] [$order_by = null] [[Description]]
	 * @param	 [[Type]] [$limit = null]		 [[Description]]
	 * @param	 [[Type]] [$where = null]		 [[Description]]
	 * @param	 [[Type]] [$select=null]		 [[Description]]
	 * @return [[Type]] [[Description]]
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

	protected function switch_database($which) {
		if ($which == 'read' && $this->read_database) {
			$this->_database = $this->read_database;
		} elseif ($which == 'write' && $this->write_database) {
			$this->_database = $this->write_database;
		}

		return $this;
	}

	public function with_deleted() {
		$this->temporary_with_deleted = true;

		return $this;
	}

	public function only_deleted() {
		$this->temporary_only_deleted = true;

		return $this;
	}

	public function restore($id) {
		/* set */
		$data['is_deleted'] = 0;

		if ($this->stamps) {
			$this->_add_fields_on_update($data);
		}

		$this->_database->update($this->table, $data, $this->create_where($id,true));

		/* delete cache data with same cache prefix as this object */
		$this->delete_cache_by_tags()->log_last_query();

		return (int) $this->_database->affected_rows();
	}

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

	protected function where_can_read() {
		$this->_database->where_in('read_role_id',array_keys(user::roles()));

		return $this;
	}

	protected function where_can_edit() {
		$this->_database->where_in('edit_role_id',array_keys(user::roles()));

		return $this;
	}

	protected function where_can_delete() {
		$this->_database->where_in('delete_role_id',array_keys(user::roles()));

		return $this;
	}

	protected function _get_userid() {
		$user_id = NOBODY_USER_ID;
	
		if (is_object(ci()->user)) {
			if (ci()->user->id) {
				$user_id = ci()->user->id;
			}
		}

		return $user_id;
	}

	protected function add_fields_on_insert(&$data) {
		if ($this->has_stamps) {
			$data['created_by'] = $this->_get_userid();
			$data['created_on'] = date('Y-m-d H:i:s');
			$data['created_ip'] = ci()->input->ip_address();
		}

		if ($this->has_roles) {
			if (!isset($data['read_role_id'])) {
				$data['read_role_id'] = ci()->user->user_read_role_id;
			}
	
			if (!isset($data['edit_role_id'])) {
				$data['edit_role_id'] = ci()->user->user_edit_role_id;
			}
	
			if (!isset($data['delete_role_id'])) {
				$data['delete_role_id'] = ci()->user->user_delete_role_id;
			}
		}

		return $this;
	}
	
	protected function add_fields_on_update(&$data) {
		if ($this->has_stamps) {
			$data['updated_by'] = $this->_get_userid();;
			$data['updated_on'] = date('Y-m-d H:i:s');
			$data['updated_ip'] = ci()->input->ip_address();
		}

		return $this;
	}
	
	protected function add_fields_on_delete(&$data) {
		if ($this->has_soft_delete && $this->has_stamps) {
			$data['deleted_by'] = $this->_get_userid();;
			$data['deleted_on'] = date('Y-m-d H:i:s');
			$data['deleted_ip'] = ci()->input->ip_address();
		}

		return $this;
	}

	protected function add_where_on_select(&$data) {
		if ($this->has_soft_delete) {
			if ($this->temporary_with_deleted !== true) {
				$this->_database->where('is_deleted', (($this->temporary_only_deleted) ? 1 : 0));
			}
		}

		return $this;
	}
	
	protected function add_where_on_update(&$data) {
		return $this;
	}

	protected function add_where_on_insert(&$data) {
		return $this;
	}

	public function add_soft_delete_default_columns($tablename,$connection='default') {
		require ROOTPATH.'/application/config/database.php';

		$config = $db[$connection];
		
		$mysqli = new mysqli($config['hostname'],$config['username'],$config['password'],$config['database']);
		
		$mysqli->query('ALTER TABLE `'.$tablename.'` ADD COLUMN is_deleted TINYINT(1) UNSIGNED NULL DEFAULT 0');

		echo 'finished';
	}

	public function add_role_default_columns($tablename,$connection='default') {
		require ROOTPATH.'/application/config/database.php';

		$config = $db[$connection];
		
		$mysqli = new mysqli($config['hostname'],$config['username'],$config['password'],$config['database']);
		
		$mysqli->query('ALTER TABLE `'.$tablename.'` ADD COLUMN read_role_id INT(11) UNSIGNED NULL DEFAULT '.ADMIN_ROLE_ID);
		$mysqli->query('ALTER TABLE `'.$tablename.'` ADD COLUMN edit_role_id INT(11) UNSIGNED NULL DEFAULT '.ADMIN_ROLE_ID);
		$mysqli->query('ALTER TABLE `'.$tablename.'` ADD COLUMN delete_role_id INT(11) UNSIGNED NULL DEFAULT '.ADMIN_ROLE_ID);

		echo 'finished';
	}

	public function add_stamp_default_columns($tablename,$connection='default') {
		require ROOTPATH.'/application/config/database.php';

		$config = $db[$connection];
		
		$mysqli = new mysqli($config['hostname'],$config['username'],$config['password'],$config['database']);
		
		$mysqli->query('ALTER TABLE `'.$tablename.'` ADD COLUMN created_on DATETIME NULL DEFAULT NULL');
		$mysqli->query('ALTER TABLE `'.$tablename.'` ADD COLUMN created_by INT(11) UNSIGNED NULL DEFAULT '.NOBODY_USER_ID);
		$mysqli->query('ALTER TABLE `'.$tablename.'` ADD COLUMN created_ip VARCHAR(15) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT \'0.0.0.0\'');

		$mysqli->query('ALTER TABLE `'.$tablename.'` ADD COLUMN updated_on DATETIME NULL DEFAULT NULL');
		$mysqli->query('ALTER TABLE `'.$tablename.'` ADD COLUMN updated_by INT(11) UNSIGNED NULL DEFAULT '.NOBODY_USER_ID);
		$mysqli->query('ALTER TABLE `'.$tablename.'` ADD COLUMN updated_ip VARCHAR(15) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT \'0.0.0.0\'');

		if ($this->has_soft_delete) {
			$mysqli->query('ALTER TABLE `'.$tablename.'` ADD COLUMN deleted_on DATETIME NULL DEFAULT NULL');
			$mysqli->query('ALTER TABLE `'.$tablename.'` ADD COLUMN deleted_by INT(11) UNSIGNED NULL DEFAULT '.NOBODY_USER_ID);
			$mysqli->query('ALTER TABLE `'.$tablename.'` ADD COLUMN deleted_ip VARCHAR(15) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT \'0.0.0.0\'');
	
			$mysqli->query('ALTER TABLE `'.$tablename.'` ADD COLUMN is_deleted TINYINT(1) UNSIGNED NULL DEFAULT 0');
		}

		echo 'finished';
	}
	
} /* end class */