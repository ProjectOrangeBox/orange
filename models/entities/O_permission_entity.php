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
 * Database Record Entity for a Permissions
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

class O_permission_entity extends \Database_model_entity
{
	/**
	 * record id
	 *
	 * @var int
	 */
	public $id;

	/**
	 * record key
	 *
	 * @var string
	 */
	public $key;

	/**
	 * record group
	 *
	 * @var string
	 */
	public $group;

	/**
	 * record description
	 *
	 * @var string
	 */
	public $description;

	/**
	 *
	 * Makes it possible to get roles as a variable
	 *
	 * @access public
	 *
	 * @param string $name
	 *
	 * @return mixed
	 *
	 * #### Example
	 * ```php
	 * $roles = $record->roles;
	 * ```
	 */
	public function __get(string $name)
	{
		switch ($name) {
			case 'roles':
				return $this->roles();
			break;
		}
	}

	/**
	 *
	 * Get the roles using this Permission
	 *
	 * @access public
	 *
	 * @return array
	 *
	 */
	public function roles() : array
	{
		return ci()->o_permission_model->roles($this->id);
	}
} /* end class */
