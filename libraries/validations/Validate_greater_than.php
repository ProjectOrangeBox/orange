<?php
/**
 * Validate_greater_than
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
 * @help is greater than the parameter value or not numeric.
 *
 */
class Validate_greater_than extends \Validate_base
{
	public function validate(&$field, string $options = '') : bool
	{
		$this->error_string = '%s must contain a number greater than %s.';

		if (!is_numeric($field)) {
			return false;
		}

		return is_numeric($field) ? ($field > $options) : false;
	}
}
