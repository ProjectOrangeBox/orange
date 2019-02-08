<?php
/**
 * O_permission_entity
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
class O_permission_entity extends model_entity {
	public $id;
	public $key;
	public $group;
	public $description;

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
				return $this->roles();
			break;
		}
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
		return ci()->o_permission_model->roles($this->id);
	}
}
