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
 * core: cache, load, dbforge
 * libraries:
 * models:
 * helpers:
 * functions: delete_cache_by_tags
 *
 */

	/*
	upsert example
	
	if (!$dbr = $this->exists(['item_no_core'=>$item_no_core])) {
		# insert

		$results = $this->insert(['item_no_core'=>$item_no_core,'foo'=>'bar']);
	} else {
		# update
		$dbr->foo = 'bar';
		
		$results = $this->update($dbr);
	}	
	*/
 
class Database_model extends MY_Model {
	/**
	 * The database connection object. Will be set to the default
	 * connection. This allows individual models to use different DBs
	 * without overwriting CI's global $this->db connection.
	 */
	protected $_database; /* connection to database resource */

	protected $db_group       = null; /* database config group to use */
	protected $read_group     = null; /* read only database group */
	protected $write_grup     = null; /* write database group */
	protected $read_database  = null; /* read only database resource */
	protected $write_database = null; /* write database resource */

	protected $table; /* table name - this is also used as the resource object name */

	protected $debug = false; /* path to debug file to write - used naturally for local debugging */

	protected $caches = []; /* place for internal page caches */
	protected $cache_prefix; /* this is auto generated in the constructor */

	protected $created_on_column_name = 'created_at';
	protected $created_by_column_name = 'created_by';
	protected $created_ip_column_name = 'created_ip';
	protected $has_created = false;

	protected $updated_on_column_name = 'updated_on';
	protected $updated_by_column_name = 'updated_by';
	protected $updated_ip_column_name = 'updated_ip';
	protected $has_updated = false;

	protected $deleted_on_column_name = 'deleted_on';
	protected $deleted_by_column_name = 'deleted_by';
	protected $deleted_ip_column_name = 'deleted_ip';
	protected $has_deleted = false;

	protected $soft_delete_key = 'is_deleted';
	protected $soft_delete     = false;

	protected $read_role_column_name   = 'read_role_id';
	protected $has_read_role           = false;

	protected $edit_role_column_name   = 'edit_role_id';
	protected $has_edit_role           = false;

	protected $delete_role_column_name = 'delete_role_id';
	protected $has_delete_role         = false;

	/* internal */
	protected $_temporary_with_deleted = false;
	protected $_temporary_only_deleted = false;

	/* This model's connection (resource or other) */
	protected $connection = null;

	/* This model's default primary key or unique identifier. Used by the get(),update() and delete() functions. */
	protected $primary_key = 'id';
	
	/* auto remove the primary key from inserts */
	protected $remove_primary_on_insert = true;

	/* database row entity */
	protected $entity = null; /* location of entity class file ie. entities/user_entity.php */

	protected $single_column_name    = null;
	protected $additional_cache_tags = ''; /* example '.tag1.tag2.tag' don't forget the first "." */
	
	protected $skip_rules = false; /* wether to skip all rule validation (probably because you don't have rules) */
	
	protected $return_false_nothing_found = false; /* boolean */
	protected $temp_return_false_nothing_found = false;

	/* Initialize the model, tie into the CodeIgniter super-object */
	public function __construct() {
		parent::__construct();

		/* the parent model expects a more generic object "name" */
		$this->object = strtolower($this->table);

		$this->cache_prefix = 'database.' . $this->object . $this->additional_cache_tags;

		/* use a custom database connection or default? */
		if (isset($this->db_group)) {
			$this->_database = $this->load->database($this->db_group, true);
		} else {
			$this->_database = $this->db;
		}

		if (isset($this->read_group)) {
			$this->read_database = $this->load->database($this->read_group, true);
		}

		if (isset($this->write_group)) {
			$this->write_database = $this->load->database($this->write_group, true);
		}

		/* let's load this models entity if they are using one */
		if ($this->entity) {
			/* load root the entity this does not need to be attached to the CI super global */
			require_once 'Model_entity.php';

			$pathinfo = pathinfo($this->entity);

			/* load supplied entity */
			require_once 'models/' . $pathinfo['dirname'] . '/' . ucfirst($pathinfo['filename']) . '.php';

			/* get the entity name */
			$this->entity = $pathinfo['filename'];
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

	public function return_false_nothing_found($value=true) {
		$this->temp_return_false_nothing_found = $value;
	
		return $this;
	}

	/**
	 * [[Description]]
	 * @author Don Myers
	 * @return [[Type]] [[Description]]
	 */
	public function get_cache_prefix() {
		return $this->cache_prefix;
	}

	/**
	 * Getter for the table name
	 * @author Don Myers
	 * @return [[Type]] [[Description]]
	 */
	public function get_tablename() {
		return $this->table;
	}

	/**
	 * [[Description]]
	 * @author Don Myers
	 * @return [[Type]] [[Description]]
	 */
	public function get_soft_delete() {
		return $this->soft_delete;
	}

	/**
	 * [[Description]]
	 * @author Don Myers
	 * @return [[Type]] [[Description]]
	 */
	public function get_primary_key() {
		return $this->primary_key;
	}

	/**
	 * [[Description]]
	 * @author Don Myers
	 * @param	 [[Type]] $name [[Description]]
	 * @return [[Type]] [[Description]]
	 */
	public function column($name) {
		$this->single_column_name = $name;

		return $this;
	}

	/**
	 * Fetch a single record based on the primary key. Returns an object.
	 * @author Don Myers
	 * @param	 [[Type]] [$primary_value=null] [[Description]]
	 * @return [[Type]] [[Description]]
	 */
	public function get($primary_value = null) {
		return ($primary_value === null) ? ($this->entity) ? new $this->entity() : stdClass() : $this->get_by([$this->primary_key => $primary_value]);
	}

	/**
	 * Fetch a single record based on an arbitrary WHERE call. Can be
	 * any valid value to $this->_database->where().
	 */

	/**
	 * [[Description]]
	 * @author Don Myers
	 * @param	 [[Type]] [$where=null] [[Description]]
	 * @return [[Type]] [[Description]]
	 */
	public function get_by($where = null) {
		if ($where) {
			$this->_database->where($where);
		}

		return $this->get_query(false);
	}

	/**
	 * Fetch all the records in the table. Can be used as a generic call
	 * to $this->_database->get() with scoped methods.
	 */

	/**
	 * [[Description]]
	 * @author Don Myers
	 * @return [[Type]] [[Description]]
	 */
	public function get_many() {
		return $this->get_many_by();
	}

	/**
	 * Fetch an array of records based on an arbitrary WHERE call.
	 */

	/**
	 * [[Description]]
	 * @author Don Myers
	 * @param	 [[Type]] [$where=null] [[Description]]
	 * @return [[Type]] [[Description]]
	 */
	public function get_many_by($where = null) {
		if ($where) {
			$this->_database->where($where);
		}

		$this->where_soft_delete();

		return $this->get_query(true);
	}

	/**
	 * Insert a new row into the table. $data should be an associative array of data to be inserted. Returns newly created ID.
	 * @author Don Myers
	 * @param	 [[Type]] $data [[Description]]
	 * @return [[Type]] [[Description]]
	 */
	public function insert($data) {
		$this->switch_database('write');

		$data = (array)$data;

		/* unset the primary key if it's set in the data array */
		if (isset($data[$this->primary_key]) && $this->remove_primary_on_insert) {
			unset($data[$this->primary_key]);
		}

		$success = (!$this->skip_rules) ? $this->only_columns_with_rules($data)->validate($data) : true;

		if ($success) {
			/* delete cache data with same cache prefix as this object */
			$this->delete_cache_by_tags();

			/* remove the protected columns */
			$this->remove_columns($data, $this->protected);

			$this->add_user_n_date('created', $data)->add_umask($data);

			if (count($data)) {
				$this->_database->insert($this->table, $data);
			}

			$this->log_last_query();

			$success = (int) $this->_database->insert_id();
		}

		return $success;
	}

	/**
	 * Updated a record based on the primary value.
	 * @author Don Myers
	 * @param	 [[Type]] $data [[Description]]
	 * @return [[Type]] [[Description]]
	 */
	public function update($data) {
		/*
		this uses the primary key
		if it's not set then set it to null so the required rule on a dynamically generated rule set picks it up
		*/
		 
		$data = (array)$data;
		
		/* is the primary key present? */
		if (!isset($data[$this->primary_key])) {
			show_error('Database Model update primary key missing');
		}

		return $this->update_by($data, [$this->primary_key => $data[$this->primary_key]]);
	}

	/**
	 * Updated a record based on an arbitrary WHERE clause.
	 * @author Don Myers
	 * @param	 [[Type]] $data				[[Description]]
	 * @param	 [[Type]] [$where=[]] [[Description]]
	 * @return [[Type]] [[Description]]
	 */
	public function update_by($data, $where = []) {
		$this->switch_database('write');

		$data = (array)$data;

		$success = (!$this->skip_rules) ? $this->only_columns_with_rules($data)->validate($data) : true;

		/*
		unset the primary key field because we are either
		using that or don't want a mass update on the
		primary which will cause a error because it should be unique
		*/
		unset($data[$this->primary_key]);

		if ($success) {
			/* delete cache data with same cache prefix as this object */
			$this->delete_cache_by_tags();

			/* remove the protected columns */
			$this->remove_columns($data, $this->protected);

			$this->add_user_n_date('updated', $data)->add_umask($data);

			if (count($data)) {
				$this->_database->where($where)->update($this->table, $data);
			}

			$this->log_last_query();

			$success = (int) $this->_database->affected_rows();
		}

		return $success;
	}

	/**
	 * Delete a row from the table by the primary value
	 * @author Don Myers
	 * @param	 [[Type]] $data [[Description]]
	 * @return [[Type]] [[Description]]
	 */
	public function delete($data) {
		/*
		this uses the primary key
		if it's not set then set it to null so the required rule on a dynamically generated rule set picks it up
 	  */
		if (is_object($data)) {
			$data = (array)$data;
		}
		
		if (is_array($data)) {
			/* is the primary key present? */
			if (!isset($data[$this->primary_key])) {
				show_error('Database Model delete primary key missing');
			}
		} elseif (is_scalar($data)) {
			$data[$this->primary_key] = $data;
		}
		
		return $this->delete_by($data);
	}

	/**
	 * Delete a row from the database table by an arbitrary WHERE clause
	 * @author Don Myers
	 * @param	 [[Type]] $data [[Description]]
	 * @return [[Type]] [[Description]]
	 */
	public function delete_by($data) {
		$this->switch_database('write');

		$data = (array)$data;

		$success = (!$this->skip_rules) ? $this->validate($data) : true;

		if ($success) {
			/* delete cache data with same cache prefix as this object */
			$this->delete_cache_by_tags();

			/* save the name/value pairs as the where clause */
			$where = $data;

			if ($this->soft_delete) {
				$this->add_user_n_date('deleted', $data);

				$success = $this->_database->where($where)->set((array) $data)->set([$this->soft_delete_key => 1])->update($this->table);
			} else {
				$this->_database->where($where)->delete($this->table);
			}

			$this->log_last_query();

			$success = (int) $this->_database->affected_rows();
		}

		return $success;
	}

	/**
	 * [[Description]]
	 * @author Don Myers
	 * @param	 [[Type]] $data		[[Description]]
	 * @param	 [[Type]] $column [[Description]]
	 * @return [[Type]] [[Description]]
	 */
	public function exists($where) {
		$this->switch_database('read');
		
		/* if they aren't sending in a array then it must be the primary id */
		if (!is_array($where)) {
			$where = [$this->primary_key=>$where];
		}

		$dbc = $this->_database->get_where($this->table,$where,1);

		$this->log_last_query();

		return ($dbc->num_rows() > 0) ? $this->format_result($dbc,false) : false;
	}

	/* used by form validation to find unique */

	/**
	 * [[Description]]
	 * @author Don Myers
	 * @param	 [[Type]] $field		[[Description]]
	 * @param	 [[Type]] $column		[[Description]]
	 * @param	 [[Type]] $form_key [[Description]]
	 * @return boolean	[[Description]]
	 */
	public function is_uniquem($field, $column, $form_key) {
		$this->switch_database('read');

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

	/**
	 * [[Description]]
	 * @author Don Myers
	 * @param	 [[Type]] $column_name				[[Description]]
	 * @param	 [[Type]] [$match = null]			[[Description]]
	 * @param	 [[Type]] [$not_match = null] [[Description]]
	 * @return [[Type]] [[Description]]
	 */
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

	/**
	 * [[Description]]
	 * @author Don Myers
	 */
	public function log_last_query() {
		if ($this->debug) {
			$query  = $this->_database->last_query();
			$output = (is_array($query)) ? print_r($query, true) : $query;
			file_put_contents(ROOTPATH.'/'.$this->debug, $output . chr(10), FILE_APPEND);
		}
	}

	/**
	 * Don't care about soft deleted rows on the next call
	 * @author Don Myers
	 * @return [[Type]] [[Description]]
	 */
	public function with_deleted() {
		$this->_temporary_with_deleted = true;

		return $this;
	}

	/**
	 * Only get deleted rows on the next call
	 * @author Don Myers
	 * @return [[Type]] [[Description]]
	 */
	public function only_deleted() {
		$this->_temporary_only_deleted = true;

		return $this;
	}

	/**
	 * Restore Set soft delete column to null again
	 * @author Don Myers
	 * @param	 [[Type]] $id [[Description]]
	 * @return boolean	[[Description]]
	 */
	public function restore($id) {
		$this->switch_database('write');

		if ($this->soft_delete) {
			return $this->update($id, [$this->soft_delete_key => 0]);
		}

		return false;
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
	 *  'foo_catalog'=>['array_key'=>'id{defaults to primary id}','select'=>'id,color{defaults to *}','where'=>['soft_delete'=>0]{defaults to none},'order_by'=>'color [asc|desc]'{defaults to none}],
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
		$dbc = $this->get_query(true); /* return multiple */

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
		$this->where_soft_delete();

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

		return $this->get_query(true);
	}

	/**
	 * [[Description]]
	 * @author Don Myers
	 * @return [[Type]] [[Description]]
	 */
	public function count() {
		/* count with no where clause */
		return $this->count_by();
	}

	/**
	 * Fetch a count of rows based on an arbitrary WHERE call.
	 * @author Don Myers
	 * @param	 [[Type]] [$where=null] [[Description]]
	 * @return [[Type]] [[Description]]
	 */
	public function count_by($where = null) {
		$this->switch_database('read');

		if ($where) {
			$this->_database->where($where);
		}

		$this->where_soft_delete();

		$result = $this->_database->select("count('" . $this->primary_key . "') as codeigniter_column_count")->get($this->table);

		$dbr = $result->result()[0];

		return $dbr->codeigniter_column_count;
	}


	/**
	 * if the results should be a array then $multiple = true else if you expect a single record then $multiple = false
	 * @author Don Myers
	 * @param	 [[Type]] $dbc							 [[Description]]
	 * @param	 [[Type]] [$multiple = true] [[Description]]
	 * @return [[Type]] [[Description]]
	 */
	public function format_result($dbc, $multiple = true) {
		$result = ($multiple) ? $this->_multiple($dbc) : $this->_single($dbc);

		if ($this->single_column_name && is_object($result)) {
			$result = $result->{$this->single_column_name};
		}

		/* clear this out to not interfere with the next query */
		$this->single_column_name = null;
		$this->temp_return_false_nothing_found = null;

		return $result;
	}

	/**
	 * [[Description]]
	 * @author Don Myers
	 * @param	 [[Type]] $which [[Description]]
	 * @return [[Type]] [[Description]]
	 */
	protected function switch_database($which) {
		if ($which == 'read' && $this->read_database) {
			$this->_database = $this->read_database;
		} elseif ($which == 'write' && $this->write_database) {
			$this->_database = $this->write_database;
		}

		return $this;
	}

	protected function where_can_view() {
		$this->_database->where_in('view_role_id',user::roles());

		return $this;
	}

	protected function where_can_edit() {
		$this->_database->where_in('edit_role_id',user::roles());

		return $this;
	}

	protected function where_can_delete() {
		$this->_database->where_in('delete_role_id',user::roles());

		return $this;
	}

	/**
	 * set soft delete as tinyint `o_is_deleted` tinyint(1) unsigned DEFAULT 0,
	 * @author Don Myers
	 * @return [[Type]] [[Description]]
	 */
	protected function where_soft_delete() {
		if ($this->soft_delete && $this->_temporary_with_deleted !== TRUE) {
			$this->_database->where($this->soft_delete_key, (($this->_temporary_only_deleted) ? 1 : 0));
		}

		return $this; /* allow query chaining */
	}

	/**
	 * [[Description]]
	 * @author Don Myers
	 * @param	 [[Type]] [$as_array=true] [[Description]]
	 * @param	 [[Type]] [$table=null]		 [[Description]]
	 * @return [[Type]] [[Description]]
	 */
	protected function get_query($as_array = true, $table = null) {
		$this->switch_database('read');

		$table = ($table) ? $table : $this->table;

		$dbc = $this->_database->get($table);

		$this->log_last_query();

		/* get returns a single object so return the first record or an empty record */
		return $this->format_result($dbc, $as_array);
	}

	protected function _multiple($dbc) {
		/* default return */
		$return = ($this->return_false_nothing_found) ? false : [];
	
		/* is there a temp value set? */
		if ($this->temp_return_false_nothing_found) {
			$return = false;
		}
	
		/* multiple records */
		if (!is_object($dbc)) {
			$result = $return;
		} else {
			if ($this->entity) {
				$result = ($dbc->num_rows()) ? $dbc->custom_result_object($this->entity) : $return;
			} else {
				$result = ($dbc->num_rows()) ? $dbc->result() : $return;
			}
		}
	
		return $result;
	}
	
	protected function _single($dbc) {
		/* default return */
		$return = ($this->return_false_nothing_found) ? false : (object)[];
	
		/* is there a temp value set? */
		if ($this->temp_return_false_nothing_found) {
			$return = false;
		}

		/* single record */
		if ($this->entity) {
			$result = ($dbc->num_rows()) ? $dbc->custom_row_object(0, $this->entity) : new $this->entity();
		} else {
			$result = ($dbc->num_rows()) ? $dbc->row() : $return;
		}
		
		return $result;
	}

	/**
	 * [[Description]]
	 * @author Don Myers
	 * @param	 [[Type]] $which [[Description]]
	 * @param	 [[Type]] &$data [[Description]]
	 * @return [[Type]] [[Description]]
	 */
	protected function add_user_n_date($which, &$data) {
		if (!in_array($which, ['created', 'updated', 'deleted'])) {
			throw new Exception('unknown value "' . $which . '" in "' . __METHOD__ . '"');
		}

		$var_is_on = 'has_' . $which;

		$var_name1 = $which . '_by_column_name';
		$var_name2 = $which . '_on_column_name';
		$var_name3 = $which . '_ip_column_name';

		if ($this->$var_is_on) {

			/* do they even have a user object? */
			if (!empty($this->$var_name1) && is_object($this->user)) {
				$data[$this->$var_name1] = $this->user->id;
			}

			/* add the date */
			if (!empty($this->$var_name2)) {
				$data[$this->$var_name2] = date('Y-m-d H:i:s');
			}

			/* set ip address */
			if (!empty($this->$var_name3)) {
				$data[$this->$var_name3] = $this->input->ip_address();
			}

		}

		return $this;
	}

	protected function add_umask(&$data) {
		if ($this->has_read_role) {
			$data[$this->read_role_column_name] = ci()->user->user_read_role_id;
		}
	
		if ($this->has_edit_role) {
			$data[$this->edit_role_column_name] = ci()->user->user_edit_role_id;
		}

		if ($this->has_delete_role) {
			$data[$this->delete_role_column_name] = ci()->user->user_delete_role_id;
		}
	
		return $this;
	}

	/**
	 * [[Description]]
	 * @author Don Myers
	 */
	protected function delete_cache_by_tags() {
		delete_cache_by_tags(explode('.', $this->cache_prefix));
	}

} /* end DB Model */