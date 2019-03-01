<?php
/**
 * Validate_is_natural_no_zero
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
 * @help contains a natural number, but not zero: 1, 2, 3, etc.
 *
 */
class Validate_is_natural_no_zero extends \Validate_base
{
	public function validate(&$field, string $options = '') : bool
	{
		$this->error_string = '%s must only contain digits and must be greater than zero.';

		return (bool)($field != 0 && ctype_digit((string) $field));
	}
}
