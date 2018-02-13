<?php
/**
 * O_role_entity
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
class O_role_entity extends model_entity {
	public $id;
	public $description;
	public $name;

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
			case 'users':
				return $this->users();
			break;
			case 'permissions':
				return $this->permissions();
			break;
		}
	}

/**
 * add_permission
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
	public function add_permission($permission) {
		return ci()->o_role_model->add_permission((int)$this->id, $permission);
	}

/**
 * remove_permission
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
	public function remove_permission($permission) {
		return ci()->o_role_model->remove_permission((int)$this->id, $permission);
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
		return ci()->o_role_model->permissions((int)$this->id);
	}

/**
 * users
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
	public function users() {
		return ci()->o_role_model->users((int)$this->id);
	}
}
