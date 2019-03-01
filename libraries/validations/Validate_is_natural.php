<?php
/**
 * Validate_is_natural
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
 * @help contains a natural number: 0, 1, 2, 3, etc.
 *
 */
class Validate_is_natural extends \Validate_base
{
	public function validate(&$field, string $options = '') : bool
	{
		$this->error_string = '%s must only contain digits.';

		return (bool)ctype_digit((string) $field);
	}
}
