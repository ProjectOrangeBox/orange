<?php 

trait Database_stamp_model_trait {
	#	protected $has_stamps = false;

	public function Database_stamp_model_trait__construct() {
		$this->trait_events['add fields insert'][] = function(&$data) {
			$data['created_by'] = (is_object(ci()->user)) ? ci()->user->id : NOBODY_USER_ID;
			$data['created_on'] = date('Y-m-d H:i:s');
			$data['created_ip'] = ci()->input->ip_address();
		};
		
		$this->trait_events['add fields update'][] = function(&$data) {
			$data['updated_by'] = (is_object(ci()->user)) ? ci()->user->id : NOBODY_USER_ID;
			$data['updated_on'] = date('Y-m-d H:i:s');
			$data['updated_ip'] = ci()->input->ip_address();
		};
		
		if ($this->uses['Database_soft_delete_model_trait']) {
			$this->trait_events['add fields delete'][] = function(&$data) {
				$data['deleted_by'] = (is_object(ci()->user)) ? ci()->user->id : NOBODY_USER_ID;
				$data['deleted_on'] = date('Y-m-d H:i:s');
				$data['deleted_ip'] = ci()->input->ip_address();
			};
		}
	}

	public function _add_stamp_default_columns($tablename,$soft_delete=false,$connection='default') {
		require ROOTPATH.'/application/config/database.php';

		$config = $db[$connection];
		
		$mysqli = new mysqli($config['hostname'],$config['username'],$config['password'],$config['database']);
		
		$mysqli->query('ALTER TABLE `'.$tablename.'` ADD COLUMN created_on DATETIME NULL DEFAULT NULL');
		$mysqli->query('ALTER TABLE `'.$tablename.'` ADD COLUMN created_by INT(11) UNSIGNED NULL DEFAULT '.NOBODY_ROLE_ID);
		$mysqli->query('ALTER TABLE `'.$tablename.'` ADD COLUMN created_ip VARCHAR(15) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT \'0.0.0.0\'');

		$mysqli->query('ALTER TABLE `'.$tablename.'` ADD COLUMN updated_on DATETIME NULL DEFAULT NULL');
		$mysqli->query('ALTER TABLE `'.$tablename.'` ADD COLUMN updated_by INT(11) UNSIGNED NULL DEFAULT '.NOBODY_ROLE_ID);
		$mysqli->query('ALTER TABLE `'.$tablename.'` ADD COLUMN updated_ip VARCHAR(15) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT \'0.0.0.0\'');

		if ($this->uses['Database_soft_delete_model_trait']) {
			$mysqli->query('ALTER TABLE `'.$tablename.'` ADD COLUMN deleted_on DATETIME NULL DEFAULT NULL');
			$mysqli->query('ALTER TABLE `'.$tablename.'` ADD COLUMN deleted_by INT(11) UNSIGNED NULL DEFAULT '.NOBODY_ROLE_ID);
			$mysqli->query('ALTER TABLE `'.$tablename.'` ADD COLUMN deleted_ip VARCHAR(15) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT \'0.0.0.0\'');
	
			$mysqli->query('ALTER TABLE `'.$tablename.'` ADD COLUMN is_deleted TINYINT(1) UNSIGNED NULL DEFAULT 0');
		}

		echo 'finished';
	}

} /* end trait */
