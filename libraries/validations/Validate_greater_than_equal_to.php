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

class Validate_greater_than_equal_to extends Validate_base {
	public function validate(&$field, $options) {
		$this->error_string = '%s must contain a number greater than or equal to %s.';

		if (!is_numeric($field)) {
			return false;
		}

		return is_numeric($field) ? ($field >= $options) : false;
	}
} /* end file */