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

class Validate_max_length extends Validate_base {
	public function validate(&$field, $options) {
		$this->error_string = '%s cannot exceed %s characters in length.';

		if (!is_numeric($options)) {
			return false;
		}

		return (MB_ENABLED === TRUE) ? ($options >= mb_strlen($field)) : ($options >= strlen($field));
	}
} /* end file */