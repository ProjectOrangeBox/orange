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

class Validate_valid_emails extends Validate_base {
	public function validate($field, $options) {
		$this->error_string = '%s must contain all valid email addresses.';

		if (strpos($field, ',') === FALSE) {
			return $this->valid_email(trim($field));
		}

		foreach (explode(',', $field) as $email) {
			if (trim($email) !== '' && $this->valid_email(trim($email)) === FALSE) {
				return false;
			}
		}

		return true;
	}

	public function valid_email($field, $options) {
		$this->error_string = '%s must contain a valid email address.';

		if (function_exists('idn_to_ascii') && $atpos = strpos($field, '@')) {
			$field = substr($field, 0, ++$atpos).idn_to_ascii(substr($field, $atpos));
		}

		return (bool) filter_var($field, FILTER_VALIDATE_EMAIL);
	}
} /* end file */