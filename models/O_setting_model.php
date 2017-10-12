<?php
/**
 * Orange Framework Extension
 *
 * This content is released under the MIT License (MIT)
 *
 * @package	CodeIgniter / Orange
 * @author Don Myers
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 *
 *
 * required
 * core: session, load, input
 * libraries: event
 * models:
 * helpers:
 * functions: setting
 *
 */

class o_setting_model extends Database_model {
	protected $table = 'orange_settings';
	protected $rules = [
		'id'             => ['field' => 'id', 'label' => 'Id', 'rules' => 'required|integer|max_length[10]|less_than[4294967295]|filter_int[10]'],
		'group'          => ['field' => 'group', 'label' => 'Group', 'rules' => 'required|max_length[64]|filter_input[64]|trim'],
		'name'           => ['field' => 'name', 'label' => 'Name', 'rules' => 'required|max_length[64]|filter_input[64]|trim|is_uniquem[o_setting_model.name.id]'],
		'value'          => ['field' => 'value', 'label' => 'Value', 'rules' => 'max_length[16384]|filter_textarea[16384]'],
		'read_role_id'   => ['field' => 'read_role_id', 'label' => 'Read Role', 'rules' => 'required|integer|max_length[10]|less_than[4294967295]|filter_int[10]'],
		'edit_role_id'   => ['field' => 'edit_role_id', 'label' => 'Edit Role', 'rules' => 'required|integer|max_length[10]|less_than[4294967295]|filter_int[10]'],
		'delete_role_id' => ['field' => 'delete_role_id', 'label' => 'Delete Role', 'rules' => 'required|integer|max_length[10]|less_than[4294967295]|filter_int[10]'],
		'enabled'        => ['field' => 'enabled', 'label' => 'Enabled', 'rules' => 'if_empty[0]|in_list[0,1]|filter_int[1]|max_length[1]|less_than[2]'],
		'help'           => ['field' => 'help', 'label' => 'Help', 'rules' => 'max_length[255]|filter_input[255]'],
		'options'        => ['field' => 'options', 'label' => 'Options', 'rules' => 'max_length[16384]|filter_textarea[16384]'],
	];
	protected $rule_sets = [
		'insert' => 'group,name,value,help,enabled,options,read_role_id,edit_role_id,delete_role_id',
	];

	/* override parent */
	protected function delete_cache_by_tags() {
		/* anytime the model calls this to flush the cache also tell the loader which creates it's own cache */
		$this->config->flush();

		parent::delete_cache_by_tags();
	}

} /* end class */