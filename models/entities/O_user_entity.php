<?php
/**
 * O_user_entity
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
class O_user_entity extends \Database_model_entity
{
	/**
	 * record id
	 *
	 * @var int
	 */
	public $id;

	/**
	 * record email
	 *
	 * @var string
	 */
	public $email;

	/**
	 * record username
	 *
	 * @var string
	 */
	public $username;

	/**
	 * record dashboard url
	 * This can be used to override the default and take the user to a custom dashboard when they login
	 *
	 * @var string
	 */
	public $dashboard_url;

	/**
	 * record active / inactive status
	 *
	 * @var bool
	 */
	public $is_active;

	/**
	 * records user read role id
	 * when a record is created what read role should it have?
	 *
	 * @var int
	 */
	public $user_read_role_id;

	/**
	 * records user edit role id
	 * when a record is created what edit role should it have?
	 *
	 * @var int
	 */
	public $user_edit_role_id;

	/**
	 * records user delete role id
	 * when a record is created what delete role should it have?
	 *
	 * @var int
	 */
	public $user_delete_role_id;

	/**
	 * records read role id
	 * who can see this record
	 *
	 * @var int
	 */
	public $read_role_id;

	/**
	 * records edit role id
	 * who can edit this record
	 *
	 * @var int
	 */
	public $edit_role_id;

	/**
	 * records delete role id
	 * who can delete this record
	 *
	 * @var int
	 */
	public $delete_role_id;

	/**
	 * Entities roles
	 *
	 * @var array
	 */
	protected $roles       = [];

	/**
	 * Entities permissions
	 *
	 * @var array
	 */
	protected $permissions = [];

	/**
	 * tracks internally whether the roles and permissions have been attached
	 *
	 * @var bool
	 */
	protected $lazy_loaded = false;

	/**
	 *
	 * Makes it possible to get roles or permissions as a variable
	 *
	 * @access public
	 *
	 * @param string $name
	 *
	 * @throws
	 * @return
	 *
	 * #### Example
	 * ```
	 *
	 * ```
	 */
	public function __get(string $name)
	{
		switch ($name) {
		case 'roles':
			$this->_lazy_load();
			return $this->roles;
			break;
		case 'permissions':
			$this->_lazy_load();
			return $this->permissions;
			break;
		}
	}

	public function refresh() : void
	{
		$this->lazy_loaded = false;
	}

	/**
	 *
	 * Return the roles attach to this user
	 *
	 * @access public
	 *
	 * @return array
	 *
	 */
	public function roles() : array
	{
		$this->_lazy_load();

		return $this->roles;
	}

	/**
	 *
	 * Returns Boolean whether the user has this role or not
	 *
	 * @access public
	 *
	 * @param $role_id
	 *
	 * @return bool
	 *
	 */
	public function has_role(int $role_id) : bool
	{
		$this->_lazy_load();

		return array_key_exists($role_id, $this->roles);
	}

	/**
	 *
	 * Returns Boolean whether the user had this permission
	 *
	 * @access public
	 *
	 * @param string $resource
	 *
	 * @return bool
	 *
	 */
	public function can(string $resource) : bool
	{
		$this->_lazy_load();

		return (in_array($resource, $this->permissions, true));
	}

	/**
	 *
	 * Return the permissions attach to this user
	 *
	 * @access public
	 *
	 * @return array
	 *
	 */
	public function permissions() : array
	{
		$this->_lazy_load();

		return $this->permissions;
	}

	/**
	 *
	 * Determine if the user has ALL of the passed roles
	 *
	 * @access public
	 *
	 * @param array $roles
	 *
	 * @return bool
	 *
	 */
	public function has_roles(array $roles) : bool
	{
		foreach ($roles as $r) {
			if (!$this->has_role($r)) {
				return false;
			}
		}

		return true;
	}

	/**
	 *
	 * Determine if the user has one of the passed roles
	 *
	 * @access public
	 *
	 * @param array $roles
	 *
	 * @return bool
	 *
	 */
	public function has_one_role_of(array $roles) : bool
	{
		foreach ((array) $roles as $r) {
			if ($this->has_role($r)) {
				return true;
			}
		}

		return false;
	}

	/**
	 *
	 * Determine if the user has ALL of the passed permissions
	 *
	 * @access public
	 *
	 * @param array $permissions
	 *
	 * @return bool
	 *
	 */
	public function has_permissions(array $permissions) : bool
	{
		foreach ($permissions as $p) {
			if ($this->cannot($p)) {
				return false;
			}
		}

		return true;
	}

	/**
	 *
	 * Determine if the user has one of the passed permissions
	 *
	 * @access public
	 *
	 * @param array $permissions
	 *
	 * @return bool
	 *
	 */
	public function has_one_permission_of(array $permissions) : bool
	{
		foreach ($permissions as $p) {
			if ($this->can($p)) {
				return true;
			}
		}

		return false;
	}

	/**
	 *
	 * Determine if the user has the passed permission
	 *
	 * @access public
	 *
	 * @param string $resource
	 *
	 * @return bool
	 *
	 */
	public function has_permission(string $permission) : bool
	{
		return $this->can($permission);
	}

	/**
	 *
	 * Determine if the user does not have the passed permission
	 *
	 * @access public
	 *
	 * @param string $resource
	 *
	 * @return bool
	 *
	 */
	public function cannot(string $permission) : bool
	{
		return !$this->can($permission);
	}

	/**
	 *
	 * Determine if the user is logged in
	 *
	 * @access public
	 *
	 * @return bool
	 *
	 */
	public function logged_in()
	{
		return ($this->id != NOBODY_USER_ID);
	}

	/**
	 *
	 * Determine if the user has the admin role
	 *
	 * @access public
	 *
	 * @return bool
	 *
	 */
	public function is_admin() : bool
	{
		return $this->has_role(ADMIN_ROLE_ID);
	}

	/**
	 *
	 * Internal lazy load the roles and permissions only after they have been called the first time.
	 *
	 * @access protected
	 *
	 * @return void
	 *
	 */
	protected function _lazy_load() : void
	{
		$user_id = (int)$this->id;
		$cache_key = 'database.user_entity.'.$user_id.'.acl.php';
		if (!$this->lazy_loaded) {
			if (!$roles_permissions = ci('cache')->get($cache_key)) {
				$roles_permissions = $this->_internal_query($user_id);
				$roles_permissions['roles'][EVERYONE_ROLE_ID] = 'Everyone';
				ci('cache')->save($cache_key, $roles_permissions, ci('cache')->ttl());
			}
			$this->roles       = (array) $roles_permissions['roles'];
			$this->permissions = (array) $roles_permissions['permissions'];
			$this->lazy_loaded = true;
		}
	}

	/**
	 *
	 * Used internally by _lazy_load
	 *
	 * @access protected
	 *
	 * @param $user_id
	 *
	 * @return array
	 *
	 */
	protected function _internal_query(int $user_id) : array
	{
		$roles_permissions = [];

		$sql = "select
			`user_id`,
			`".config('auth.role table')."`.`id` `orange_roles_id`,
			`".config('auth.role table')."`.`name` `orange_roles_name`,
			`permission_id`,
			`key`
			from ".config('auth.user role table')."
			left join ".config('auth.role table')." on ".config('auth.role table').".id = ".config('auth.user role table').".role_id
			left join ".config('auth.role permission table')." on ".config('auth.role permission table').".role_id = ".config('auth.role table').".id
			left join ".config('auth.permission table')." on ".config('auth.permission table').".id = ".config('auth.role permission table').".permission_id
			where ".config('auth.user role table').".user_id = ".$user_id;

		$dbc = ci('db')->query($sql);

		foreach ($dbc->result() as $dbr) {
			if ($dbr->orange_roles_name) {
				if (!empty($dbr->orange_roles_name)) {
					$roles_permissions['roles'][(int) $dbr->orange_roles_id] = $dbr->orange_roles_name;
				}
			}
			if ($dbr->key) {
				if (!empty($dbr->key)) {
					$roles_permissions['permissions'][(int) $dbr->permission_id] = $dbr->key;
				}
			}
		}

		return $roles_permissions;
	}
} /* end class */
