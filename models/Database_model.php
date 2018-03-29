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
	protected $db_group = null;
	protected $read_db_group = null;
	protected $write_db_group = null;
	protected $table;
	protected $protected = [];
	protected $debug = false;
	protected $primary_key = 'id';
	protected $additional_cache_tags = '';
	protected $skip_rules = false;
	protected $entity = null;
	protected $has_roles = false;
	protected $has_stamps = false;
	protected $has_soft_delete = false;
	protected $default_return_on_many;
	protected $default_return_on_single;
	protected $read_database = null;
	protected $write_database = null;
	protected $_database;
	protected $cache_prefix;
	protected $temporary_with_deleted = false;
	protected $temporary_only_deleted = false;
	protected $temporary_column_name = null;
	protected $temporary_return_as_array = null;
	protected $auto_generated_primary = true;
	protected $read_role_id = null;
	protected $edit_role_id = null;
	protected $delete_role_id = null;

/**
 * __construct
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
	public function __construct() {
		parent::__construct();
		$this->object = strtolower($this->table);
		$this->cache_prefix = trim('database.'.$this->object.'.'.trim($this->additional_cache_tags,'.'),'.');
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
				'read_role_id' => ['field' => 'read_role_id', 'label' => 'Read Role', 	'rules' => 'required|integer|max_length[10]|less_than[4294967295]|filter_int[10]'],
				'edit_role_id' => ['field' => 'edit_role_id', 'label' => 'Edit Role', 	'rules' => 'required|integer|max_length[10]|less_than[4294967295]|filter_int[10]'],
				'delete_role_id' => ['field' => 'delete_role_id', 'label' => 'Delete Role', 'rules' => 'required|integer|max_length[10]|less_than[4294967295]|filter_int[10]'],
			];
		}
		$this->default_return_on_many = [];
		if ($this->entity) {
			$this->entity = ($this->entity === true) ? ucfirst(strtolower(substr(get_class($this),0,-5)).'entity') : $this->entity;
			if (!class_exists($this->entity,true)) {
				log_message('error', 'Non-existent class: '.$this->entity);
				throw new Exception('Non-existent class: '.$this->entity);
			}
			$this->default_return_on_single = new $this->entity();
		} else {
			$this->default_return_on_single = new stdClass();
		}
		log_message('info', 'Database_model Class Initialized');
	}

/**
 * __call
 * Insert description here
 *
 * @param $name
 * @param $arguments
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function __call($name, $arguments) {
		if (method_exists($this->_database,$name)) {
			call_user_func_array([$this->_database,$name],$arguments);
		}
		return $this;
	}

/**
 * set_role_ids
 * Insert description here
 *
 * @param $read_id
 * @param $edit_id
 * @param $delete_id
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function set_role_ids($read_id=null,$edit_id=null,$delete_id=null) {
		if ($read_id) {
			$this->read_role_id = $read_id;
		}
		if ($edit_id) {
			$this->edit_role_id = $edit_id;
		}
		if ($delete_id) {
			$this->delete_role_id = $delete_id;
		}
	}

/**
 * get_cache_prefix
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
	public function get_cache_prefix() {
		return $this->cache_prefix;
	}

/**
 * get_tablename
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
	public function get_tablename() {
		return $this->table;
	}

/**
 * get_primary_key
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
	public function get_primary_key() {
		return $this->primary_key;
	}

/**
 * get_soft_delete
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
	public function get_soft_delete() {
		return $this->has_soft_delete;
	}

/**
 * as_array
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
	public function as_array() {
		$this->temporary_return_as_array = true;
		return $this;
	}

/**
 * column
 * Insert description here
 *
 * @param $name
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function column($name) {
		$this->temporary_column_name = $name;
		return $this;
	}

/**
 * on_empty_return
 * Insert description here
 *
 * @param $return
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function on_empty_return($return) {
		$this->default_return_on_single	= $return;
		$this->default_return_on_many	= $return;
		return $this;
	}

/**
 * get
 * Insert description here
 *
 * @param $primary_value
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function get($primary_value = null) {
		return ($primary_value === null) ? $this->default_return_on_single : $this->get_by([$this->primary_key => $primary_value]);
	}

/**
 * get_by
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
	public function get_by($where = null) {
		if ($where) {
			$this->_database->where($where);
		}
		return $this->_get(false);
	}

/**
 * get_many
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
	public function get_many() {
		return $this->get_many_by();
	}

/**
 * get_many_by
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
	public function get_many_by($where = null) {
		if ($where) {
			$this->_database->where($where);
		}
		return $this->_get(true);
	}

/**
 * _get
 * Insert description here
 *
 * @param $as_array
 * @param $table
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	protected function _get($as_array = true, $table = null) {
		$this->switch_database('read');
		$table = ($table) ? $table : $this->table;
		$this->add_where_on_select();
		$dbc = $this->_database->get($table);
		$this->log_last_query();
		$results = ($as_array) ? $this->_as_array($dbc) : $this->_as_row($dbc);
		if ($this->temporary_column_name) {
			if (is_array($results)) {
				$results = $results[$this->temporary_column_name];
			} elseif (is_object($results)) {
				$results = $results->{$this->temporary_column_name};
			}
		}
		$this->_clear();
		return $results;
	}

/**
 * _clear
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
	public function _clear() {
		$this->temporary_column_name = null;
		$this->temporary_return_as_array = null;
		$this->default_return_on_many = [];
		$this->default_return_on_single = ($this->entity) ? new $this->entity() : new stdClass();
		return $this;
	}

/**
 * insert
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
	public function insert($data) {
		$this->switch_database('write');
		$data = (array)$data;
		if ($this->auto_generated_primary) {
			unset($data[$this->primary_key]);
		}
		$success = (!$this->skip_rules && count($this->rules)) ? $this->only_columns($data,$this->rules)->add_rule_set_columns($data,'insert')->validate($data) : true;
		if ($success) {
			$this->remap_columns($data, $this->rules)->remove_columns($data, $this->protected);
			$this->add_fields_on_insert($data)->add_where_on_insert($data);
			if (count($data)) {
				$this->_database->insert($this->table, $data);
			}
			$this->delete_cache_by_tags()->log_last_query();
			$success = (int) $this->_database->insert_id();
		}
		$this->_clear();
		return $success;
	}

/**
 * add_rule_set_columns
 * Make sure we add the correct rule set and add the missing data entries
 *
 * @param $data
 * @param $which_set
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
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
 * update
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
	public function update($data) {
		$data = (array)$data;
		if (!isset($data[$this->primary_key])) {
			throw new Exception('Database Model update primary key missing');
		}
		return $this->update_by($data, [$this->primary_key => $data[$this->primary_key]]);
	}

/**
 * update_by
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
	public function update_by($data, $where = []) {
		$this->switch_database('write');
		$data = (array)$data;
		$success = (!$this->skip_rules && count($this->rules)) ? $this->only_columns($data,$this->rules)->add_rule_set_columns($data,'update')->validate($data) : true;
		unset($data[$this->primary_key]);
		if ($success) {
			$this->remap_columns($data, $this->rules)->remove_columns($data, $this->protected);
			$this->add_fields_on_update($data)->add_where_on_update($data);
			if (count($data)) {
				$this->_database->where($where)->update($this->table, $data);
			}
			$this->delete_cache_by_tags()->log_last_query();
			$success = (int) $this->_database->affected_rows();
		}
		$this->_clear();
		return $success;
	}

/**
 * delete
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
	public function delete($arg) {
		return $this->delete_by($this->create_where($arg,true));
	}

/**
 * delete_by
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
	public function delete_by($data) {
		$this->switch_database('write');
		$data = (array)$data;
		$success = (!$this->skip_rules && count($this->rules)) ? $this->only_columns($data,$this->rules)->add_rule_set_columns($data,'delete')->validate($data) : true;
		if ($success) {
			$this->remap_columns($data, $this->rules);
			if ($this->has_soft_delete) {
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

/**
 * _as_array
 * Insert description here
 *
 * @param $dbc
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	protected function _as_array($dbc) {
		$result = $this->default_return_on_many;
		if (is_object($dbc)) {
			if ($dbc->num_rows()) {
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
 * _as_row
 * Insert description here
 *
 * @param $dbc
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	protected function _as_row($dbc) {
		$result = $this->default_return_on_single;
		if (is_object($dbc)) {
			if ($dbc->num_rows()) {
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
 * log_last_query
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
	protected function log_last_query() {
		if ($this->debug) {
			$query  = $this->_database->last_query();
			$output = (is_array($query)) ? print_r($query, true) : $query;
			file_put_contents(ROOTPATH.'/var/logs/model.'.get_called_class().'.log',$output.chr(10), FILE_APPEND);
		}
		return $this;
	}

/**
 * delete_cache_by_tags
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
	protected function delete_cache_by_tags() {
		delete_cache_by_tags($this->cache_prefix);
		return $this;
	}

/**
 * catalog
 * Insert description here
 *
 * @param $array_key
 * @param $select_columns
 * @param $where
 * @param $order_by
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function catalog($array_key = null, $select_columns = null, $where = null, $order_by = null) {
		$results = [];
		$single_column = false;
		$array_key = ($array_key) ? $array_key : $this->primary_key;
		$select_columns = is_array($select_columns) ? implode(',',$select_columns) : $select_columns;
		if ($select_columns === null || $select_columns == '*') {
			$select = '*';
		} else {
			$select = $array_key.','.$select_columns;
			if (strpos($select_columns,',') === false) {
				$single_column = $select_columns;
			}
		}
		$this->_database->select($select);
		if ($where) {
			$this->_database->where($where);
		}
		if ($order_by) {
			if (strpos($order_by,' ') === false) {
				$this->_database->order_by($order_by);
			} else {
				list($order_by,$direction) = explode($order_by,' ',2);
				$this->_database->order_by($order_by,$direction);
			}
		}
		$dbc = $this->_get(true);
		foreach ($dbc as $dbr) {
			if ($single_column) {
				$results[$dbr->$array_key] = $dbr->$single_column;
			} else {
				$results[$dbr->$array_key] = $dbr;
			}
		}
		return $results;
	}

/**
 * is_uniquem
 * Insert description here
 *
 * @param $field
 * @param $column
 * @param $form_key
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function is_uniquem($field, $column, $form_key) {
		$dbc = $this->_database
			->select($column.','.$this->primary_key)
			->where($column, $field)
			->get($this->table, 3);
		$rows_found = $dbc->num_rows();
		if ($rows_found == 0) {
			return true;
		}
		if ($rows_found > 1) {
			return false;
		}
		return ($dbc->row()->{$this->primary_key} == $this->input->request($form_key));
	}

/**
 * build_sql_boolean_match
 * Insert description here
 *
 * @param $column_name
 * @param $match
 * @param $not_match
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function build_sql_boolean_match($column_name, $match = null, $not_match = null) {
		$sql = false;
		$match_where = '';
		if (is_array($match) > 0) {
			$match_where .= ' +'.implode(' +', $match);
		}
		if (is_array($not_match) > 0) {
			$match_where .= ' -'.implode(' -', $not_match);
		}
		if (!empty($match_where)) {
			$sql = "match(`".$column_name."`) against('".trim($match_where)."' in boolean mode)";
		}
		return $sql;
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
		$record = $this->get_by($this->create_where($arg));
		return (isset($record->{$this->primary_key})) ? $record : false;
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
		if ($this->stamps) {
			$this->_add_fields_on_update($data);
		}
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
		$this->_database->where_in('read_role_id',array_keys(user::roles()));
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
		$this->_database->where_in('edit_role_id',array_keys(user::roles()));
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
		$this->_database->where_in('delete_role_id',array_keys(user::roles()));
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
			if (!isset($data['read_role_id'])) {
				$data['read_role_id'] = ((int)$this->read_role_id > 0) ? (int)$this->read_role_id : (int)ci()->user->user_read_role_id;
			}
			if (!isset($data['edit_role_id'])) {
				$data['edit_role_id'] = ((int)$this->edit_role_id > 0) ? (int)$this->edit_role_id : (int)ci()->user->user_edit_role_id;
			}
			if (!isset($data['delete_role_id'])) {
				$data['delete_role_id'] = ((int)$this->delete_role_id > 0) ? (int)$this->delete_role_id : (int)ci()->user->user_delete_role_id;
			}
		}
		return $this;
	}

/**
 * Insert description here
 *
 * @param array $data list of data to test
 * @param array $only_columns list of only the columns you want to check. if left empty all columns in $data tested
 *
 * @return boolean|array false if no changes or associated list of columns changed. list key contains the column name and list value contain the old value
 *
 */
	protected function find_changed_columns($data,$only_columns=false) {
		$changed = false;
		$data = (array)$data;

		if (!isset($data[$this->primary_key])) {
			throw new Exception('Database Model update primary key missing');
		}

		$old_data = (array)$this->get($data[$this->primary_key]);
		$columns = (is_array($only_columns)) ? $only_columns : array_keys($data);

		foreach ($columns as $column) {
			if ($data[$column] != $old_data[$column]) {
				$changed[$column] = $old_data[$column];
			}
		}

		return $changed;
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

/**
 * add_soft_delete_default_columns
 * Insert description here
 *
 * @param $tablename
 * @param $connection
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function add_soft_delete_default_columns($tablename,$connection='default') {
		require APPPATH.'/config/database.php';
		$config = $db[$connection];
		$mysqli = new mysqli($config['hostname'],$config['username'],$config['password'],$config['database']);
		$mysqli->query('ALTER TABLE `'.$tablename.'` ADD COLUMN is_deleted TINYINT(1) UNSIGNED NULL DEFAULT 0');
		echo 'finished';
	}

/**
 * add_role_default_columns
 * Insert description here
 *
 * @param $tablename
 * @param $connection
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function add_role_default_columns($tablename=null,$connection='default') {
		$tablename = ($tablename) ? $tablename : $this->table;
		require APPPATH.'/config/database.php';
		$config = $db[$connection];
		$mysqli = new mysqli($config['hostname'],$config['username'],$config['password'],$config['database']);
		$mysqli->query('ALTER TABLE `'.$tablename.'` ADD COLUMN read_role_id INT(11) UNSIGNED NULL DEFAULT '.ADMIN_ROLE_ID);
		$mysqli->query('ALTER TABLE `'.$tablename.'` ADD COLUMN edit_role_id INT(11) UNSIGNED NULL DEFAULT '.ADMIN_ROLE_ID);
		$mysqli->query('ALTER TABLE `'.$tablename.'` ADD COLUMN delete_role_id INT(11) UNSIGNED NULL DEFAULT '.ADMIN_ROLE_ID);
		echo '<p>finished</p>';
	}

/**
 * add_stamp_default_columns
 * Insert description here
 *
 * @param $tablename
 * @param $connection
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function add_stamp_default_columns($tablename=null,$connection='default') {
		$tablename = ($tablename) ? $tablename : $this->table;
		require APPPATH.'/config/database.php';
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
		echo '<p>finished</p>';
	}
}
