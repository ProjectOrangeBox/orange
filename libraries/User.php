<?php
/*
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
 */

class User {
	public static function add_role($role) {
		return ci('user')->add_role($role);
	}

	public static function remove_role($role) {
		return ci('user')->remove_role($role);
	}

	public static function roles() {
		return ci('user')->roles();
	}

	public static function has_role($role_id) {
		return ci('user')->has_role($role_id);
	}

	public static function has_roles($role_ary = []) {
		return ci('user')->has_roles($role_ary);
	}

	public static function has_one_role_of($role_ary = []) {
		return ci('user')->has_one_role_of($role_ary);
	}

	public static function permissions() {
		return ci('user')->permissions();
	}

	public static function has_permissions($permission_ary = []) {
		return ci('user')->has_permissions($permission_ary);
	}

	public static function has_one_permission_of($permission_ary = []) {
		return ci('user')->has_one_permission_of($permission_ary);
	}

	public static function can($resource) {
		return ci('user')->can($resource);
	}

	public static function has_permission($resource) {
		return ci('user')->has_permission($resource);
	}

	public static function cannot($resource) {
		return ci('user')->cannot($resource);
	}

	public static function logged_in() {
		return ci('user')->logged_in();
	}

	public static function is_admin() {
		return ci('user')->is_admin();
	}

	public static function email() {
		return ci('user')->email;
	}

	public static function id() {
		return ci('user')->id;
	}

	public static function username() {
		return ci('user')->username;
	}

	public static function sudo($username) {
		ci('user')->username = $username;
	}

} /* end file */
