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
 * required
 * core: session, load, input
 * libraries: event
 * models:
 * helpers:
 * functions: setting
 *
 */
 
class o_permission_model extends Database_model {
	protected $table; /* this is retrieved in the constructor from the config file */
	protected $entity                = 'entities/permission_entity';
	protected $additional_cache_tags = '.acl';

	protected $has_created           = true;
	protected $has_updated           = true;

	protected $has_read_role           = true;
	protected $has_edit_role           = true;
	protected $has_delete_role         = true;

	protected $rules = [
		'id'          => ['field' => 'id', 'label' => 'Id', 'rules' => 'required|integer|max_length[10]|less_than[4294967295]|filter_int[10]'],
		'description' => ['field' => 'description', 'label' => 'Description', 'rules' => 'required|max_length[255]|filter_input[255]|is_uniquem[o_permission_model.description.id]'],
		'group'       => ['field' => 'group', 'label' => 'Group', 'rules' => 'required|max_length[255]|filter_input[255]'],
		'key'         => ['field' => 'key', 'label' => 'Key', 'rules' => 'required|strtolower|max_length[255]|filter_input[255]|is_uniquem[o_permission_model.key.id]'],

		'read_role_id'   => ['field' => 'read_role_id', 'label' => 'Read Role', 'rules' => 'required|integer|max_length[10]|less_than[4294967295]|filter_int[10]'],
		'edit_role_id'   => ['field' => 'edit_role_id', 'label' => 'Edit Role', 'rules' => 'required|integer|max_length[10]|less_than[4294967295]|filter_int[10]'],
		'delete_role_id' => ['field' => 'delete_role_id', 'label' => 'Delete Role', 'rules' => 'required|integer|max_length[10]|less_than[4294967295]|filter_int[10]'],
	];
	protected $rule_sets = [
		'insert' => 'group,key,description',
	];

	public function __construct() {
		$this->table = config('auth.permission table');
	
		parent::__construct();
	}

	public function roles($role) {
		$dbc = $this->_database
			->from(config('auth.role permission table'))
			->join(config('auth.role table'), config('auth.role table') . '.id = ' . config('auth.role permission table') . '.role_id')
			->where(['permission_id' => (int) $role_id])
			->get();

		return ($this->_database->num_rows() > 0) ? $dbc->result() : [];
	}

	public function _find_role_id($role) {
		return (int) ((int) $role > 0) ? $role : $this->o_role_model->column('id')->get_by(['name' => $role]);
	}

	public function _find_permission_id($permission) {
		return (int) ((int) $permission > 0) ? $permission : $this->o_permission_model->column('id')->get_by(['key' => $permission]);
	}
	
	public function insert($data) {
		if (!$this->exists(['key'=>$data['key']])) {
			parent::insert($data);
			
			/* add this to the administrator role */
			$this->administrator_refresh();
			
			/* refresh the ACL caches */
			$this->delete_cache_by_tags();
		}
	}

	public function administrator_refresh() {
		/* get all permissions */
		$records = $this->get_many();
		
		foreach ($records as $record) {
			$this->o_role_model->add_permission(ADMIN_ROLE_ID,$record->id);
		}
	}

} /* end class */