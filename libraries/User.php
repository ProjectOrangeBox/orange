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
 * functions:
 *
 * Static Library (Wrapper)
 */

class User {
	protected static $user;

	/* call static functions on the user entity if they are there */
	/**
	 * __callStatic function.
	 *
	 * @access public
	 * @static
	 * @param mixed $name
	 * @param mixed $arguments
	 * @return void
	 */
	public static function __callStatic($name, $arguments) {
		if (is_object(ci()->user)) {
			if (property_exists(ci()->user, $name)) {
				return ci()->user->$name;
			} elseif (method_exists(ci()->user, $name)) {
				return ci()->user->$name($arguments[0]);
			}
		}

		return false;
	}
	
	public static function attach_user(&$user) {
		self::$user = $user;
	}

} /* end class */