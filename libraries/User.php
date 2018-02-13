<?php
/**
 * User
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
class User {
	public static function add_role($role) {
		return ci('user')->add_role($role);
	}

/**
 * remove_role
 * Insert description here
 *
 * @param $role
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public static function remove_role($role) {
		return ci('user')->remove_role($role);
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
	public static function roles() {
		return ci('user')->roles();
	}

/**
 * has_role
 * Insert description here
 *
 * @param $role_id
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public static function has_role($role_id) {
		return ci('user')->has_role($role_id);
	}

/**
 * has_roles
 * Insert description here
 *
 * @param $role_ary
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public static function has_roles($role_ary = []) {
		return ci('user')->has_roles($role_ary);
	}

/**
 * has_one_role_of
 * Insert description here
 *
 * @param $role_ary
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public static function has_one_role_of($role_ary = []) {
		return ci('user')->has_one_role_of($role_ary);
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
	public static function permissions() {
		return ci('user')->permissions();
	}

/**
 * has_permissions
 * Insert description here
 *
 * @param $permission_ary
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public static function has_permissions($permission_ary = []) {
		return ci('user')->has_permissions($permission_ary);
	}

/**
 * has_one_permission_of
 * Insert description here
 *
 * @param $permission_ary
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public static function has_one_permission_of($permission_ary = []) {
		return ci('user')->has_one_permission_of($permission_ary);
	}

/**
 * can
 * Insert description here
 *
 * @param $resource
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public static function can($resource) {
		return ci('user')->can($resource);
	}

/**
 * has_permission
 * Insert description here
 *
 * @param $resource
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public static function has_permission($resource) {
		return ci('user')->has_permission($resource);
	}

/**
 * cannot
 * Insert description here
 *
 * @param $resource
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public static function cannot($resource) {
		return ci('user')->cannot($resource);
	}

/**
 * logged_in
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
	public static function logged_in() {
		return ci('user')->logged_in();
	}

/**
 * is_admin
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
	public static function is_admin() {
		return ci('user')->is_admin();
	}

/**
 * email
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
	public static function email() {
		return ci('user')->email;
	}

/**
 * id
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
	public static function id() {
		return ci('user')->id;
	}

/**
 * username
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
	public static function username() {
		return ci('user')->username;
	}

/**
 * sudo
 * Insert description here
 *
 * @param $username
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public static function sudo($username) {
		ci('user')->username = $username;
	}
}
