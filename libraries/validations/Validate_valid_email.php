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

class Validate_valid_email extends Validate_base {
	public function validate(&$field, $options) {
		$this->error_string = '%s must contain a valid email address.';

		if (count(explode('@', $field)) !== 2) {
			return false;
		}

		if (function_exists('idn_to_ascii') && $atpos = strpos($field, '@')) {
			$field = substr($field, 0, ++$atpos).idn_to_ascii(substr($field, $atpos));
		}

		return (bool) filter_var($field, FILTER_VALIDATE_EMAIL);
	}
} /* end file */
