<?php
/**
 * User
 * Static wrapper for CodeIgniter User Entity.
 * ci('user') or ci()->user
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
 * models: o_user_model
 * helpers:
 * functions:
 *
 */
class User {
	public static function __callStatic($name,$arguments) {
		if (method_exists(ci('user'),$name)) {
			return (isset($arguments[0])) ? ci('user')->$name($arguments[0]) : ci('user')->$name();
		} elseif (property_exists(ci('user'),$name)) {
			return ci('user')->$name;
		} else {
			throw new Exception('User property or method '.$name.' not available on user entity.');
		}
	}
}
