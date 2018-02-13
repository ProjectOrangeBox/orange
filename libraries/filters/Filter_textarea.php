<?php
/**
 * Filter_textarea
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
class Filter_textarea extends Filter_base {
	public function filter(&$field, $options) {
		$this->field($field)->human_plus()->length($options);
	}
}
