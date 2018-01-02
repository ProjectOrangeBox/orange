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

class O_user_entity extends model_entity {
	public $id;
	public $email;
	public $username;
	protected $roles       = [];
	protected $permissions = [];
	protected $lazy_loaded = false;

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

	public function add_role($role) {
		ci()->o_user_model->add_role($this->id, $role);
	}

	public function remove_role($role) {
		ci()->o_user_model->remove_role($this->id, $role);
	}

	public function roles() {
		$this->_lazy_load();

		return $this->roles;
	}

	public function has_role($role_id) {
		$this->_lazy_load();

		return array_key_exists($role_id, $this->roles);
	}

	public function can($resource) {
		$this->_lazy_load();

		return (in_array($resource, $this->permissions, true));
	}

	public function permissions() {
		$this->_lazy_load();

		return $this->permissions;
	}

	public function has_roles($role_ary = []) {
		foreach ((array) $roles_ary as $r) {
			if (!$this->has_role($r)) {
				return false;
			}
		}

		return true;
	}

	public function has_one_role_of($role_ary = []) {
		foreach ((array) $roles_ary as $r) {
			if ($this->has_role($r)) {
				return true;
			}
		}

		return false;
	}

	public function has_permissions($permission_ary = []) {
		foreach ((array) $permission_ary as $p) {
			if ($this->cannot($p)) {
				return false;
			}
		}

		return true;
	}

	public function has_one_permission_of($permission_ary = []) {
		foreach ((array) $permission_ary as $p) {
			if ($this->can($p)) {
				return true;
			}
		}

		return false;
	}

	public function has_permission($resource) {
		return $this->can($resource);
	}

	public function cannot($resource) {
		return !$this->can($resource);
	}

	public function logged_in() {
		return ($this->id !== NOBODY_USER_ID);
	}

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

} /* end file */