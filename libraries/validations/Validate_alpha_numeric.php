<?php
/**
 * Validate_alpha_numeric
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
 * @help contains anything other than alpha-numeric characters.
 *
 */
class Validate_alpha_numeric extends \Validate_base
{
	public function validate(&$field, string $options = '') : bool
	{
		$this->error_string = '%s may only contain alpha-numeric characters.';

		return (bool) ctype_alnum((string) $field);
	}
}
