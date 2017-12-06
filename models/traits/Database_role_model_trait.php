<?php 

trait Database_role_model_trait {
	#	protected $has_roles = false;

	public function Database_role_model_trait__construct() {
		$this->rules = $this->rules + [
			'read_role_id'   => ['field' => 'read_role_id', 'label' => 'Read Role', 'rules' => 'required|integer|max_length[10]|less_than[4294967295]|filter_int[10]'],
			'edit_role_id'   => ['field' => 'edit_role_id', 'label' => 'Edit Role', 'rules' => 'required|integer|max_length[10]|less_than[4294967295]|filter_int[10]'],
			'delete_role_id' => ['field' => 'delete_role_id', 'label' => 'Delete Role', 'rules' => 'required|integer|max_length[10]|less_than[4294967295]|filter_int[10]'],
		];

		$this->trait_events['add fields insert'][] = function(&$data) {
			if (!isset($data['read_role_id'])) {
				$data['read_role_id'] = ci()->user->user_read_role_id;
			}
	
			if (!isset($data['edit_role_id'])) {
				$data['edit_role_id'] = ci()->user->user_edit_role_id;
			}
	
			if (!isset($data['delete_role_id'])) {
				$data['delete_role_id'] = ci()->user->user_delete_role_id;
			}
		};
	}

	protected function where_can_read() {
		$this->_database->where_in('read_role_id',user::roles());

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

	public function _add_role_default_columns($tablename,$soft_delete=false,$connection='default') {
		require ROOTPATH.'/application/config/database.php';

		$config = $db[$connection];
		
		$mysqli = new mysqli($config['hostname'],$config['username'],$config['password'],$config['database']);
		
		$mysqli->query('ALTER TABLE `'.$tablename.'` ADD COLUMN read_role_id INT(11) UNSIGNED NULL DEFAULT '.ADMIN_ROLE_ID);
		$mysqli->query('ALTER TABLE `'.$tablename.'` ADD COLUMN edit_role_id INT(11) UNSIGNED NULL DEFAULT '.ADMIN_ROLE_ID);
		$mysqli->query('ALTER TABLE `'.$tablename.'` ADD COLUMN delete_role_id INT(11) UNSIGNED NULL DEFAULT '.ADMIN_ROLE_ID);

		echo 'finished';
	}
	
} /* end class */
