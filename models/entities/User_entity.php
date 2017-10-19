<?php
/**
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
 *
 */

class User_entity extends model_entity {
	public $id;
	public $email;
	public $username;

	protected $roles       = [];
	protected $permissions = [];
	protected $is_root = false;

	protected $lazy_loaded = false;

	/**
	 * [[Description]]
	 * @private
	 * @author Don Myers
	 * @param  [[Type]] $name [[Description]]
	 * @return [[Type]] [[Description]]
	 */
	public function __get($name) {
		switch ($name) {
		case 'is_root':
			$this->_lazy_load();
		
			return $this->is_root;
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
	 * [[Description]]
	 * @author Don Myers
	 * @param [[Type]] $role [[Description]]
	 */
	public function add_role($role) {
		ci()->o_user_model->add_role($this->id, $role);
	}

	/**
	 * [[Description]]
	 * @author Don Myers
	 * @param [[Type]] $role [[Description]]
	 */
	public function remove_role($role) {
		ci()->o_user_model->remove_role($this->id, $role);
	}

	/**
	 * [[Description]]
	 * @author Don Myers
	 * @return [[Type]] [[Description]]
	 */
	public function roles() {
		$this->_lazy_load();
	
		return $this->roles;
	}

	/**
	 * [[Description]]
	 * @author Don Myers
	 * @param  [[Type]] $role_id [[Description]]
	 * @return boolean  [[Description]]
	 */
	public function has_role($role_id) {
		$this->_lazy_load();

		/* root "has" all roles */
		if ($this->is_root === true) {
			return true;
		}

		return array_key_exists($role_id, $this->roles);
	}

	/**
	 * [[Description]]
	 * @author Don Myers
	 * @param  [[Type]] [$role_ary = []] [[Description]]
	 * @return boolean  [[Description]]
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
	 * [[Description]]
	 * @author Don Myers
	 * @param  [[Type]] [$role_ary = []] [[Description]]
	 * @return boolean  [[Description]]
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
	 * [[Description]]
	 * @author Don Myers
	 * @return [[Type]] [[Description]]
	 */
	public function permissions() {
		$this->_lazy_load();

		return $this->permissions;
	}

	/**
	 * [[Description]]
	 * @author Don Myers
	 * @param  [[Type]] [$permission_ary = []] [[Description]]
	 * @return boolean  [[Description]]
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
	 * [[Description]]
	 * @author Don Myers
	 * @param  [[Type]] [$permission_ary = []] [[Description]]
	 * @return boolean  [[Description]]
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
	 * [[Description]]
	 * @author Don Myers
	 * @param  [[Type]] $resource [[Description]]
	 * @return boolean  [[Description]]
	 */
	public function can($resource) {
		$this->_lazy_load();

		/* root "has" all permissions */
		if ($this->is_root === true) {
			return true;
		}

		return (in_array($resource, $this->permissions, true));
	}

	/**
	 * [[Description]]
	 * @author Don Myers
	 * @param  [[Type]] $resource [[Description]]
	 * @return [[Type]] [[Description]]
	 */
	public function cannot($resource) {
		return !$this->can($resource);
	}

	/**
	 * [[Description]]
	 * @author Don Myers
	 * @return [[Type]] [[Description]]
	 */
	public function is_guest() {
		/* is this person the guest id? */
		return ($this->id === config('auth.guest user id',-1));
	}

	/**
	 * [[Description]]
	 * @private
	 * @author Don Myers
	 * @return [[Type]] [[Description]]
	 */
	protected function _lazy_load() {
		if (!$this->lazy_loaded) {
			$user_id = (int) $this->id;

			ci()->config->set_item('user_id', $user_id);

			/* cache this */
			$roles_permissions = o::cache(ci()->o_user_model->get_cache_prefix() . '.' . $user_id, function (){
				/* add the roles */
				$roles_permissions = [];

				$sql = "select
					`user_id`,
					`orange_roles`.`id` `orange_roles_id`,
					`orange_roles`.`name` `orange_roles_name`,
					`permission_id`,
					`key`
					from orange_user_role
					left join orange_roles on orange_roles.id = orange_user_role.role_id
					left join orange_role_permission on orange_role_permission.role_id = orange_roles.id
					left join orange_permission on orange_permission.id = orange_role_permission.permission_id
					where orange_user_role.user_id = " . ci()->config->item('user_id');

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
			});

			$this->roles       = (array) $roles_permissions['roles'];
			$this->permissions = (array) $roles_permissions['permissions'];

			/* are they the super user? */
			if ($this->id == config('auth.root user id', -1)) {
				$this->is_root = true;
			}

			/* Do they have the super user role */
			if (in_array(config('auth.root role id', -1), $this->permissions) === true) {
				$this->is_root = true;
			}

			$this->lazy_loaded = true;
		}
	}

} /* end class */