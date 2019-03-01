<?php
/**
 * Validate_valid_emails
 * Insert description here
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2018
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version v2.0
 *
 * required
 * core:
 * libraries:
 * models:
 * helpers:
 * functions:
 *
 * @help value provided in a comma separated list are valid emails.
 *
 */
class Validate_valid_emails extends \Validate_base
{
	public function validate(&$field, string $options = '') : bool
	{
		$this->error_string = '%s must contain all valid email addresses.';
		
		foreach (explode(',', $field) as $email) {
			/* bail on first failure */
			if (trim($email) !== '' && $this->valid_email(trim($email)) === false) {
				return false;
			}
		}
		
		return true;
	}

	/**
	 *
	 * validate individual email address
	 *
	 * @access public
	 *
	 * @param string $field email address
	 *
	 * @return bool success
	 *
	 */
	public function valid_email(string $field)
	{
		$this->error_string = '%s must contain a valid email address.';
		
		if (function_exists('idn_to_ascii') && $atpos = strpos($field, '@')) {
			$field = substr($field, 0, ++$atpos).idn_to_ascii(substr($field, $atpos));
		}
		
		return (bool)filter_var($field, FILTER_VALIDATE_EMAIL);
	}
}
