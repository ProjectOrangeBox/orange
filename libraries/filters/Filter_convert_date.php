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

class Filter_convert_date extends Filter_base {
	public function filter(&$field, $options) {
		$options = ($options) ? $options : 'Y-m-d H:i:s';

		$field = date($options, strtotime($field));
	}
} /* end file */
