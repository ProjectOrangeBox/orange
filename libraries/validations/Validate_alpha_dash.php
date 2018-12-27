<?php
/**
 * Validate_alpha_dash
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
 * @help may only contain alpha characters, underscores, and dashes.
 *
 */
class Validate_alpha_dash extends Validate_base {
	public function validate(&$field, $options) {
		$this->error_string = '%s may only contain alpha characters, underscores, and dashes.';
		return (bool) preg_match('/^[a-z_-]+$/i', $field);
	}
}
