<?php
/**
 * Filter_slug
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
class Filter_slug extends Filter_base {
	public function filter(&$field, $options) {
		$field = preg_replace('~[^\pL\d]+~u', '-', $field);
		$field = iconv('utf-8', 'us-ascii//TRANSLIT', $field);
		$field = preg_replace('~[^-\w]+~', '', $field);
		$field = trim($field, '-');
		$field = preg_replace('~-+~', '-', $field);
		$field = strtolower($field);
	}
}
