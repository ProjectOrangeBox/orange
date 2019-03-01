<?php
/**
 * Validate_valid_base64
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
 * @help supplied string contains valid Base64 characters.
 *
 */
class Validate_valid_base64 extends \Validate_base
{
	public function validate(&$field, string $options = '') : bool
	{
		$this->error_string = '%s is not valid Base64.';

		return (bool)(base64_encode(base64_decode($field)) === $field);
	}
}
