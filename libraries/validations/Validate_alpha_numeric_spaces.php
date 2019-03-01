<?php
/**
 * Validate_alpha_numeric_spaces
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
 * @help contains anything other than alpha-numeric, space characters.
 *
 */
class Validate_alpha_numeric_spaces extends \Validate_base
{
	public function validate(&$field, string $options = '') : bool
	{
		$this->error_string = '%s may only contain alpha-numeric characters and spaces.';

		return (bool) preg_match('/^[A-Z0-9 ]+$/i', $field);
	}
}
