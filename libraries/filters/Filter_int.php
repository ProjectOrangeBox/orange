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

class Filter_int extends Filter_base {
	public function filter(&$field, $options) {
		$pos = strpos($field, '.');

		if ($pos !== FALSE) {
			$field = substr($field, 0, $pos);
		}

		$field  = preg_replace('/[^\-\+0-9]+/', '', $field);
		$prefix = ($field[0] == '-' || $field[0] == '+') ? $field[0] : '';
		$field  = $prefix.preg_replace('/[^0-9]+/', '', $field);

		$this->field($field)->length($options);
	}
} /* end file */