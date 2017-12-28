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

	public function pull() {
		return $this->get_many_by(['enabled' => 1]);
	}

	/* override parent */
	protected function delete_cache_by_tags() {
		/* anytime the model calls this to flush the cache also tell the core config which creates it's own cache */
		$this->config->flush();

		/* now tell the parent */
		return parent::delete_cache_by_tags();
	}

} /* end class */