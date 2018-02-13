<?php
/**
 * Filter_convert_date
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
class Filter_convert_date extends Filter_base {
	public function filter(&$field, $options) {
		$options = ($options) ? $options : 'Y-m-d H:i:s';
		$field = date($options, strtotime($field));
	}
}
