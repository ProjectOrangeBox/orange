<?php
/**
 * Validate_alpha_dash
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
 * @help validate a error hasn't already been returned
 * @help has_errors[group,field]
 * @help has_errors[group]
 * @help used to stop further rule processing in piped chain
 *
 */
class Validate_has_errors extends \Validate_base
{
	public function validate(&$field, string $options = '') : bool
	{
		if (strpos($options, ',')) {
			list($group, $field) = explode(',', $options);

			$errors = ci('errors')->as_array($group);

			$does_not_have_error = !isset($errors[$field]);
		} else {
			$does_not_have_error = !ci('errors')->has($options);
		}

		return (bool)$does_not_have_error;
	}
}
