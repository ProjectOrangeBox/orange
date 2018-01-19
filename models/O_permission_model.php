<?php
/*
 * Orange Framework Extension
 *
 * @package	CodeIgniter / Orange
 * @author Don Myers
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 *
 * required
 * core:
 * libraries:
 * models:
 * helpers:
 * functions:
 *
 */

class o_permission_model extends Database_model {
	protected $table;
	protected $additional_cache_tags = '.acl';
	protected $has_roles = true;
	protected $has_stamps = true;
	protected $entity = true;
	protected $rules = [
		'id'          => ['field' => 'id', 'label' => 'Id', 'rules' => 'required|integer|max_length[10]|less_than[4294967295]|filter_int[10]'],
		'description' => ['field' => 'description', 'label' => 'Description', 'rules' => 'required|max_length[255]|filter_input[255]|is_uniquem[o_permission_model.description.id]'],
		'group'       => ['field' => 'group', 'label' => 'Group', 'rules' => 'required|max_length[255]|filter_input[255]'],
		'key'         => ['field' => 'key', 'label' => 'Key', 'rules' => 'required|strtolower|max_length[255]|filter_input[255]|is_uniquem[o_permission_model.key.id]'],
	];

	public function __construct() {
		$this->table = config('auth.permission table');

		parent::__construct();

		log_message('info', 'o_permission_model Class Initialized');
	}

	public function roles($role) {
		$dbc = $this->_database
			->from(config('auth.role permission table'))
			->join(config('auth.role table'), config('auth.role table').'.id = '.config('auth.role permission table').'.role_id')
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
		parent::insert($data);

		$this->_refresh();

		$this->delete_cache_by_tags();
	}

	public function update($data) {
		parent::update($data);

		$this->_refresh();

		$this->delete_cache_by_tags();
	}

	public function _refresh() {
		$records = $this->get_many();

		foreach ($records as $record) {
			$this->o_role_model->add_permission(ADMIN_ROLE_ID,$record->id);
		}

		$this->o_role_model->remove_permission(NOBODY_USER_ID);
	}

	public function add($key,$group,$description) {
		$data = [
			'key' => $key,
			'group' => $group,
			'description' => $description,
			'read_role_id'=>ADMIN_ROLE_ID,
			'edit_role_id'=>ADMIN_ROLE_ID,
			'delete_role_id'=>ADMIN_ROLE_ID,
		];

		if (!$this->exists(['key'=>$key])) {
			$this->insert($data);
		}
	}

} /* end file */
