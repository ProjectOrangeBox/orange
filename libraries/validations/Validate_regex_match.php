<?php
/**
 * Validate_regex_match
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
 * @help matches the regular expression.
 *
 */
class Validate_regex_match extends \Validate_base
{
	public function validate(&$field, string $options = '') : bool
	{
		if (empty($options)) {
			$this->error_string = '%s expression match option empty.';
		
			return false;
		}

		$this->error_string = '%s is not in the correct format.';
		
		return (bool) preg_match($options, $field);
	}
}
