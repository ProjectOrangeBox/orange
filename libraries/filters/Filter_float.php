<?php
/**
 * Filter_float
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
 * @help clean for use as a float
 */
class Filter_float extends Filter_base {
	public function filter(&$field, $options) {
		$field  = preg_replace('/[^\-\+0-9.]+/', '', $field);
		$prefix = ($field[0] == '-' || $field[0] == '+') ? $field[0] : '';
		$field  = $prefix.preg_replace('/[^0-9.]+/', '', $field);
		$this->field($field)->length($options);
	}
}
