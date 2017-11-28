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

class Permission_entity extends model_entity {
	public $id;
	public $key;
	public $group;
	public $description;

	/**
	 * [[Description]]
	 * @private
	 * @author Don Myers
	 * @param  [[Type]] $name [[Description]]
	 * @return [[Type]] [[Description]]
	 */
	public function __get($name) {
		switch ($name) {
			case 'roles':
				return $this->roles();
			break;
		}
	}

	public function roles() {
		return ci()->o_permission_model->roles($this->id);
	}

} /* end class */