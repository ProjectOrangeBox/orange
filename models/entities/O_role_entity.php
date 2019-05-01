<?php
/**
 * Orange
 *
 * An open source extensions for CodeIgniter 3.x
 *
 * This content is released under the MIT License (MIT)
 * Copyright (c) 2014 - 2019, Project Orange Box
 */

/**
 * Database Record Entity for a Role
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2019
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version v2.0.0
 * @filesource
 *
 */

class O_role_entity extends \Database_model_entity
{
	/**
	 * record id
	 *
	 * @var int
	 */
	public $id;

	/**
	 * record name
	 *
	 * @var string
	 */
	public $name;

	/**
	 * record description
	 *
	 * @var string
	 */
	public $description;

	/**
	 *
	 * Makes it possible to get roles or permissions as a variable
	 *
	 * @access public
	 *
	 * @param string $name
	 *
	 * @return mixed
	 *
	 */
	public function __get(string $name)
	{
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
	 *
	 * Add a permission to this entity
	 *
	 * @access public
	 *
	 * @param $permission
	 *
	 * @return
	 *
	 */
	public function add_permission($permission)
	{
		return ci()->o_role_model->add_permission((int)$this->id, $permission);
	}

	/**
	 *
	 * Description Here
	 *
	 * @access public
	 *
	 * @param $permission
	 *
	 * @throws
	 * @return
	 *
	 * #### Example
	 * ```
	 *
	 * ```
	 */
	public function remove_permission($permission)
	{
		return ci()->o_role_model->remove_permission((int)$this->id, $permission);
	}

	/**
	 *
	 * Description Here
	 *
	 * @access public
	 *
	 * @param
	 *
	 * @throws
	 * @return
	 *
	 * #### Example
	 * ```
	 *
	 * ```
	 */
	public function permissions()
	{
		return ci()->o_role_model->permissions((int)$this->id);
	}

	/**
	 *
	 * Description Here
	 *
	 * @access public
	 *
	 * @param
	 *
	 * @throws
	 * @return
	 *
	 * #### Example
	 * ```
	 *
	 * ```
	 */
	public function users()
	{
		return ci()->o_role_model->users((int)$this->id);
	}
} /* end class */
