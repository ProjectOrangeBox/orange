<?php 

trait Database_soft_delete_model_trait {
	protected $soft_delete = true;
	protected $_temporary_with_deleted = false;
	protected $_temporary_only_deleted = false;

	public function Database_soft_delete_model_trait__construct() {
		$this->trait_events['get where'][] = function() {
			if ($this->_temporary_with_deleted !== true) {
				$this->_database->where('is_deleted', (($this->_temporary_only_deleted) ? 1 : 0));
			}
		};
	}
	
	/* override the models */
	public function delete_by($data) {
		foreach ($this->trait_events['before update'] as $event) $event();

		$data = (array)$data;

		$success = (!$this->skip_rules) ? $this->validate($data) : true;

		if ($success) {
			/* save the name/value pairs as the where clause */
			$where = $data;

			foreach ($this->trait_events['add fields delete'] as $event) {
				$event($data);
			}

			$success = $this->_database->where($where)->set($data)->set(['is_deleted'=>1])->update($this->table);

			$this->delete_cache_by_tags()->_log_last_query();

			$success = (int) $this->_database->affected_rows();
		}

		return $success;
	}

	public function get_soft_delete() {
		return $this->soft_delete;
	}

	public function with_deleted() {
		$this->_temporary_with_deleted = true;

		return $this;
	}

	public function only_deleted() {
		$this->_temporary_only_deleted = true;

		return $this;
	}

	public function restore($id) {
		foreach ($this->trait_events['before update'] as $event) $event();

		return $this->update($id,['is_deleted'=>0]);
	}

	public function _add_soft_delete_default_columns($tablename,$soft_delete=false,$connection='default') {
		require ROOTPATH.'/application/config/database.php';

		$config = $db[$connection];
		
		$mysqli = new mysqli($config['hostname'],$config['username'],$config['password'],$config['database']);
		
		$mysqli->query('ALTER TABLE `'.$tablename.'` ADD COLUMN is_deleted TINYINT(1) UNSIGNED NULL DEFAULT 0');

		echo 'finished';
	}
	
} /* end class */