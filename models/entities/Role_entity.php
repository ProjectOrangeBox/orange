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

class Role_entity extends model_entity {
	public $id;
	public $description;
	public $name;

	public function add_permission($permission) {
		ci()->o_role_model->add_permission($this->id, $permission);
	}

	public function remove_permission($permission) {
		ci()->o_role_model->remove_permission($this->id, $permission);
	}

	public function permissions() {
		ci()->o_role_model->permissions($this->id);
	}

	public function users() {
		ci()->o_role_model->users($this->id);
	}

} /* end class */