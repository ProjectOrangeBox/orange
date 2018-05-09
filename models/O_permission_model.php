<?php
/**
 * O_permission_model
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
class O_permission_model extends Database_model {
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

/**
 * __construct
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
	public function __construct() {
		$this->table = config('auth.permission table');
		parent::__construct();
		log_message('info', 'o_permission_model Class Initialized');
	}

/**
 * roles
 * Insert description here
 *
 * @param $role
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function roles($role) {
		$dbc = $this->_database
			->from(config('auth.role permission table'))
			->join(config('auth.role table'), config('auth.role table').'.id = '.config('auth.role permission table').'.role_id')
			->where(['permission_id' => (int) $role_id])
			->get();
		return ($this->_database->num_rows() > 0) ? $dbc->result() : [];
	}

/**
 * _find_role_id
 * Insert description here
 *
 * @param $role
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function _find_role_id($role) {
		return (int) ((int) $role > 0) ? $role : $this->o_role_model->column('id')->get_by(['name' => $role]);
	}

/**
 * _find_permission_id
 * Insert description here
 *
 * @param $permission
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function _find_permission_id($permission) {
		return (int) ((int) $permission > 0) ? $permission : $this->o_permission_model->column('id')->get_by(['key' => $permission]);
	}

/**
 * insert
 * Insert description here
 *
 * @param $data
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function insert($data) {
		$success = parent::insert($data);
		$this->_refresh();
		$this->delete_cache_by_tags();
		return $success;
	}

/**
 * update
 * Insert description here
 *
 * @param $data
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function update($data) {
		$success = parent::update($data);
		$this->_refresh();
		$this->delete_cache_by_tags();
		return $success;
	}

/**
 * _refresh
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
	public function _refresh() {
		$records = $this->get_many();
		foreach ($records as $record) {
			$this->o_role_model->add_permission(ADMIN_ROLE_ID,$record->id);
		}
		$this->o_role_model->remove_permission(NOBODY_USER_ID);
	}

/**
 * add
 * Insert description here
 *
 * @param $key
 * @param $group
 * @param $description
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function add($key,$group,$description) {
		$success = false;

		if (!$this->exists(['key'=>$key])) {
			$success = $this->insert(['key' => $key,	'group' => $group,'description' => $description,'read_role_id'=>ADMIN_ROLE_ID,	'edit_role_id'=>ADMIN_ROLE_ID,'delete_role_id'=>ADMIN_ROLE_ID]);
		}

		return $success;
	}
}
