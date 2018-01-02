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

class O_role_entity extends model_entity {
	public $id;
	public $description;
	public $name;

	public function __get($name) {
		switch ($name) {
			case 'users':
				return $this->users();
			break;
			case 'permissions':
				return $this->permissions();
			break;
		}
	}

	public function add_permission($permission) {
		return ci()->o_role_model->add_permission((int)$this->id, $permission);
	}

	public function remove_permission($permission) {
		return ci()->o_role_model->remove_permission((int)$this->id, $permission);
	}

	public function permissions() {
		return ci()->o_role_model->permissions((int)$this->id);
	}

	public function users() {
		return ci()->o_role_model->users((int)$this->id);
	}

} /* end class */