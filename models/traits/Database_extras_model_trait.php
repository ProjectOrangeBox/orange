<?php 

trait Database_extras_model_trait {

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
		foreach ($this->trait_events['before select'] as $event) $event();

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
		foreach ($this->trait_events['get where'] as $event) {
			$event($data);
		}

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

		return $this->_get(true);
	}

	protected function delete_cache_by_tags() {
		delete_cache_by_tags(explode('.', $this->cache_prefix));
		
		return $this;
	}

	public function count() {
		return $this->count_by();
	}

	public function count_by($where = null) {
		foreach ($this->trait_events['before select'] as $event) $event();

		if ($where) {
			$this->_database->where($where);
		}

		foreach ($this->trait_events['get where'] as $event) {
			$event();
		}

		$result = $this->_database->select("count('" . $this->primary_key . "') as codeigniter_column_count")->get($this->table);

		$dbr = $result->result()[0];

		return (int)$dbr->codeigniter_column_count;
	}

	public function exists($where) {
		foreach ($this->trait_events['before select'] as $event) $event();

		if (is_object($where) || is_array($where)) {
			$where = (array)$where;
		} elseif (is_scalar($where)) {
			$where[$this->primary_key] = $where;
		}

		$dbc = $this->_database->get_where($this->table,$where,1);

		$this->_log_last_query();

		return ($dbc->num_rows() > 0) ? $this->format_result($dbc,false) : false;
	}
			
} /* end trait */
