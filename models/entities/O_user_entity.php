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
class O_user_entity extends model_entity {
	public $id;
	public $email;
	public $username;
	protected $roles       = [];
	protected $permissions = [];
	protected $lazy_loaded = false;

/**
 * __get
 * Insert description here
 *
 * @param $name
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function __get($name) {
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

/**
 * add_role
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
	public function add_role($role) {
		ci()->o_user_model->add_role($this->id, $role);
	}

/**
 * remove_role
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
	public function remove_role($role) {
		ci()->o_user_model->remove_role($this->id, $role);
	}

/**
 * roles
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
	public function roles() {
		$this->_lazy_load();
		return $this->roles;
	}

/**
 * has_role
 * Insert description here
 *
 * @param $role_id
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function has_role($role_id) {
		$this->_lazy_load();
		return array_key_exists($role_id, $this->roles);
	}

/**
 * can
 * Insert description here
 *
 * @param $resource
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function can($resource) {
		$this->_lazy_load();
		return (in_array($resource, $this->permissions, true));
	}

/**
 * permissions
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
	public function permissions() {
		$this->_lazy_load();
		return $this->permissions;
	}

/**
 * has_roles
 * Insert description here
 *
 * @param $role_ary
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function has_roles($role_ary = []) {
		foreach ((array) $roles_ary as $r) {
			if (!$this->has_role($r)) {
				return false;
			}
		}
		return true;
	}

/**
 * has_one_role_of
 * Insert description here
 *
 * @param $role_ary
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function has_one_role_of($role_ary = []) {
		foreach ((array) $roles_ary as $r) {
			if ($this->has_role($r)) {
				return true;
			}
		}
		return false;
	}

/**
 * has_permissions
 * Insert description here
 *
 * @param $permission_ary
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function has_permissions($permission_ary = []) {
		foreach ((array) $permission_ary as $p) {
			if ($this->cannot($p)) {
				return false;
			}
		}
		return true;
	}

/**
 * has_one_permission_of
 * Insert description here
 *
 * @param $permission_ary
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function has_one_permission_of($permission_ary = []) {
		foreach ((array) $permission_ary as $p) {
			if ($this->can($p)) {
				return true;
			}
		}
		return false;
	}

/**
 * has_permission
 * Insert description here
 *
 * @param $resource
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function has_permission($resource) {
		return $this->can($resource);
	}

/**
 * cannot
 * Insert description here
 *
 * @param $resource
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function cannot($resource) {
		return !$this->can($resource);
	}

/**
 * logged_in
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
	public function logged_in() {
		return ($this->id != NOBODY_USER_ID);
	}

/**
 * is_admin
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
	public function is_admin() {
		return $this->has_role(ADMIN_ROLE_ID);
	}

/**
 * _lazy_load
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
	protected function _lazy_load() {
		$user_id = (int)$this->id;
		$cache_key = 'database.user_entity.'.$user_id.'.acl.php';
		if (!$this->lazy_loaded) {
			if (!$roles_permissions = ci()->cache->get($cache_key)) {
				$roles_permissions = $this->_lazy_loader($user_id);
				ci()->cache->save($cache_key,$roles_permissions,cache_ttl());
			}
			$this->roles       = (array) $roles_permissions['roles'];
			$this->permissions = (array) $roles_permissions['permissions'];
			$this->lazy_loaded = true;
		}
	}

/**
 * _lazy_loader
 * Insert description here
 *
 * @param $user_id
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	protected function _lazy_loader($user_id) {
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
		$dbc = ci()->db->query($sql);
		foreach ($dbc->result() as $dbr) {
			if ($dbr->orange_roles_name) {
				$roles_permissions['roles'][(int) $dbr->orange_roles_id] = $dbr->orange_roles_name;
			}
			if ($dbr->key) {
				$roles_permissions['permissions'][(int) $dbr->permission_id] = $dbr->key;
			}
		}
		return $roles_permissions;
	}
}
