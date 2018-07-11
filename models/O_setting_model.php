<?php
/**
 * O_setting_model
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
class O_setting_model extends Database_model {
	protected $table = 'orange_settings';
	protected $has_roles = true;
	protected $has_stamps = true;
	protected $rules = [
		'id'             => ['field' => 'id', 'label' => 'Id', 'rules' => 'required|integer|max_length[10]|less_than[4294967295]|filter_int[10]'],
		'group'          => ['field' => 'group', 'label' => 'Group', 'rules' => 'required|max_length[64]|filter_input[64]|trim'],
		'name'           => ['field' => 'name', 'label' => 'Name', 'rules' => 'required|max_length[64]|filter_input[64]|trim'],
		'value'          => ['field' => 'value', 'label' => 'Value', 'rules' => 'max_length[16384]|filter_textarea[16384]'],
		'enabled'        => ['field' => 'enabled', 'label' => 'Enabled', 'rules' => 'if_empty[0]|in_list[0,1]|filter_int[1]|max_length[1]|less_than[2]'],
		'help'           => ['field' => 'help', 'label' => 'Help', 'rules' => 'max_length[255]|filter_input[255]'],
		'options'        => ['field' => 'options', 'label' => 'Options', 'rules' => 'max_length[16384]|filter_textarea[16384]'],
	];

/**
 * pull
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
	public function for_config() {
		return $this->get_many_by(['enabled' => 1]);
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
		ci('config')->flush();

		return parent::delete_cache_by_tags();
	}
	
	public function migration_add($name=null,$group=null,$value=null,$help=null,$options=null,$migration=null) {
		$this->skip_rules = true;

		$defaults = [
			'read_role_id'=>ADMIN_ROLE_ID,
			'edit_role_id'=>ADMIN_ROLE_ID,
			'delete_role_id'=>ADMIN_ROLE_ID,
			'created_on'=>date('Y-m-d H:i:s'),
			'created_by'=>0,
			'created_ip'=>'0.0.0.0',
			'updated_on'=>date('Y-m-d H:i:s'),
			'updated_by'=>0,
			'updated_ip'=>'0.0.0.0',
		];

		/* we already verified the key that's the "real" primary key */
		return (!$this->exists(['name'=>$name,'group'=>$group])) ? $this->insert($defaults + ['name'=>$name,'group'=>$group,'value'=>$value,'help'=>$help,'options'=>$options,'migration'=>$migration]) : false;
	}

	public function migration_remove($where=null) {
		$this->skip_rules = true;

		if (!is_array($where)) {
			$where = ['migration'=>$where];
		}

		return $this->delete_by($where);
	}
	
}
