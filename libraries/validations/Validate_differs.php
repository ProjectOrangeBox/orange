<?php
/**
 * Validate_differs
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
 * @help Does not match the field in the parameter.
 *
 */
class Validate_differs extends \Validate_base
{
	public function validate(&$field, string $options = '') : bool
	{
		$this->error_string = '%s must differ from %s.';
		return !(isset($this->field_data[$options]) && $this->field_data[$options] === $field);
	}
}
