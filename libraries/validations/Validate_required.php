<?php
/**
 * Validate_required
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
 * @help is not empty.
 *
 */
class Validate_required extends \Validate_base
{
	public function validate(&$field, string $options = '') : bool
	{
		$this->error_string = '%s is required.';

		return is_array($field) ? (bool) count($field) : (trim($field) !== '');
	}
}
