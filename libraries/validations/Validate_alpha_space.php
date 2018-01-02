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

class Validate_alpha_space extends Validate_base {
	public function validate(&$field, $options) {
		$this->error_string = '%s may only contain alpha characters, spaces, and dashes.';

		return (bool) preg_match('/^[a-z -]+$/i', $field);
	}
} /* end file */