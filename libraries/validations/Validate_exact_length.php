<?php
/**
 * Validate_exact_length
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
 * @help is exactly the parameter value.
 *
 */
class Validate_exact_length extends \Validate_base
{
	public function validate(&$field, string $options = '') : bool
	{
		$this->error_string = '%s must be exactly %s characters in length.';

		if (!is_numeric($options)) {
			return false;
		}

		return (MB_ENABLED === true) ? (mb_strlen($field) === (int) $options) : (strlen($field) === (int) $options);
	}
}
