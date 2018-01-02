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

class Validate_valid_base64 extends Validate_base {
	public function validate(&$field, $options) {
		$this->error_string = '%s is not valid Base64.';

		return (base64_encode(base64_decode($field)) === $field);
	}
} /* end file */