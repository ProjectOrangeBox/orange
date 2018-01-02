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

class Validate_differs extends Validate_base {
	public function validate(&$field, $options) {
		$this->error_string = '%s must differ from %s.';

		return !($this->field_data[$field] === $this->field_data[$options]);
	}
} /* end file */