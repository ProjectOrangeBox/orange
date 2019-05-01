<?php
/**
 * O_role_model
 * Insert description here
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2018
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version v2.0
 *
 * required
 * core:
 * libraries:
 * models:
 * helpers:
 * functions:
 *
 */
class O_role_model extends \Database_model
{
	protected $table;
	protected $additional_cache_tags = '.acl';
	protected $entity = 'o_role_entity';
	protected $rules = [
		'id'          => ['field' => 'id', 'label' => 'Id', 'rules' => 'required|integer|max_length[10]|less_than[4294967295]|filter_int[10]'],
		'name'        => ['field' => 'name', 'label' => 'Name', 'rules' => 'required|is_uniquem[o_role_model.name.id]|max_length[64]|filter_input[64]|is_uniquem[o_role_model.name.id]'],
		'description' => ['field' => 'description', 'label' => 'Description', 'rules' => 'max_length[255]|filter_input[255]|is_uniquem[o_role_model.description.id]'],
		'migration'   => ['field' => 'migration', 'label' => 'Migration', 'rules' => 'max_length[255]'],
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
	public function __construct()
	{
		$this->table = config('auth.role table');

		parent::__construct();

		log_message('info', 'o_role_model Class Initialized');
	}

	/**
	 * add_permission
	 * Insert description here
	 *
	 * @param $role
	 * @param $permission
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function add_permission($role, $permission)
	{
		if (is_array($permission)) {
			foreach ($permission as $p) {
				$this->add_permission($role, $p);
			}
			return true;
		}

		return $this->_database->replace(config('auth.role permission table'), ['role_id' => (int) $this->_find_role_id($role), 'permission_id' => (int) $this->_find_permission_id($permission)]);
	}

	/**
	 * remove_permission
	 * Insert description here
	 *
	 * @param $role
	 * @param $permission
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function remove_permission($role, $permission = null)
	{
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

	public function delete($role_id)
	{
		parent::delete($role_id);

		return $this->_database->delete(config('auth.role permission table'), ['role_id' => (int) $this->_find_role_id($role_id)]);
	}

	/**
	 * permissions
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
	public function permissions($role)
	{
		$role_id = $this->_find_role_id($role);

		$dbc = $this->_database
			->from(config('auth.role permission table'))
			->join(config('auth.permission table'), config('auth.permission table').'.id = '.config('auth.role permission table').'.permission_id')
			->where(['role_id' => (int) $role_id])
			->get();

		return ($dbc->num_rows() > 0) ? $dbc->result() : [];
	}

	/**
	 * users
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
	public function users($role)
	{
		$role_id = $this->_find_role_id($role);

		$dbc = $this->_database
			->from(config('auth.user role table'))
			->join(config('auth.user table'), config('auth.user table').'.id = '.config('auth.user role table').'.user_id')
			->where(['role_id' => (int) $role_id])
			->get();

		return ($dbc->num_rows() > 0) ? $dbc->result() : [];
	}

	/**
	 * truncate
	 * Insert description here
	 *
	 * @param $ensure
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function truncate($ensure = false)
	{
		if ($ensure !== true) {
			throw new \Exception(__METHOD__.' please provide "true" to truncate a database model');
		}

		$this->_database->truncate(config('auth.role permission table'));
		$this->_database->truncate(config('auth.user role table'));

		return parent::truncate($ensure);
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
	public function _find_role_id($role)
	{
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
	public function _find_permission_id($permission)
	{
		return (int) ((int) $permission > 0) ? $permission : $this->o_permission_model->column('id')->get_by(['key' => $permission]);
	}

	/* migration */
	public function migration_add($name=null, $description=null, $migration=null)
	{
		$this->skip_rules = true;

		/* we already verified the name that's the "real" primary key */
		return (!$this->exists(['name'=>$name])) ? $this->insert(['name'=>$name,'description'=>$description,'migration'=>$migration]) : false;
	}

	public function migration_remove(string $migration=null) : bool
	{
		$this->skip_rules = true;

		unset($this->has['delete_role']);

		return $this->delete_by(['migration'=>$migration]);
	}
}
