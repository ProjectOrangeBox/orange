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

class Validate_matches extends Validate_base {
	public function validate(&$field, $options) {
		$this->error_string = '%s does not match %s.';
		return isset($this->field_data[$options]) ? ($field === $this->field_data[$options]) : false;
	}
} /* end file */