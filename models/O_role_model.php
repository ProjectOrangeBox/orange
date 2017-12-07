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
 
class o_role_model extends Database_model {
	protected $table; /* this is retrieved in the constructor from the config file */
	protected $additional_cache_tags = '.acl';
	protected $has_roles = true;
	protected $has_stamps = true;
	protected $entity = true;
	protected $debug = true;

	protected $rules = [
		'id'          => ['field' => 'id', 'label' => 'Id', 'rules' => 'required|integer|max_length[10]|less_than[4294967295]|filter_int[10]'],
		'name'        => ['field' => 'name', 'label' => 'Name', 'rules' => 'required|is_uniquem[o_role_model.name.id]|max_length[64]|filter_input[64]|is_uniquem[o_role_model.name.id]'],
		'description' => ['field' => 'description', 'label' => 'Description', 'rules' => 'max_length[255]|filter_input[255]|is_uniquem[o_role_model.description.id]'],
	];

	public function __construct() {
		$this->table = config('auth.role table');
	
		parent::__construct();
	}

	public function add_permission($role, $permission) {
		if (is_array($permission)) {
			foreach ($permission as $p) {
				$this->add_permission($role, $p);
			}

			return true;
		}

		return $this->_database->replace(config('auth.role permission table'), ['role_id' => (int) $this->_find_role_id($role), 'permission_id' => (int) $this->_find_permission_id($permission)]);
	}

	public function remove_permission($role, $permission = null) {
		if (is_array($permission)) {
			foreach ($permission as $p) {
				$this->remove_permission($role, $p);
			}

			return true;
		}

		if ($permission === null) {
			$this->_database->delete(config('auth.role permission table'), ['role_id' => (int) $this->_find_role_id($role)]);

			return true;
		}

		return $this->_database->delete(config('auth.role permission table'), ['role_id' => (int) $this->_find_role_id($role), 'permission_id' => (int) $this->_find_permission_id($permission)]);
	}

	/* get permissions for this role */
	public function permissions($role) {
		$role_id = $this->_find_role_id($role);

		$dbc = $this->_database
			->from(config('auth.role permission table'))
			->join(config('auth.permission table'), config('auth.permission table') . '.id = ' . config('auth.role permission table') . '.permission_id')
			->where(['role_id' => (int) $role_id])
			->get();

		return ($dbc->num_rows() > 0) ? $dbc->result() : [];
	}

	/* get users with this role */
	public function users($role) {
		$role_id = $this->_find_role_id($role);

		$dbc = $this->_database
			->from(config('auth.user role table'))
			->join(config('auth.user table'), config('auth.user table') . '.id = ' . config('auth.user role table') . '.user_id')
			->where(['role_id' => (int) $role_id])
			->get();

		return ($dbc->num_rows() > 0) ? $dbc->result() : [];
	}

	public function truncate($ensure = false) {
		if ($ensure !== true) {
			throw new Exception(__METHOD__ . ' please provide "true" to truncate a database model');
		}

		$this->_database->truncate(config('auth.role permission table'));
		$this->_database->truncate(config('auth.user role table'));

		return parent::truncate($ensure);
	}

	public function _find_role_id($role) {
		return (int) ((int) $role > 0) ? $role : $this->o_role_model->column('id')->get_by(['name' => $role]);
	}

	public function _find_permission_id($permission) {
		return (int) ((int) $permission > 0) ? $permission : $this->o_permission_model->column('id')->get_by(['key' => $permission]);
	}

} /* end class */