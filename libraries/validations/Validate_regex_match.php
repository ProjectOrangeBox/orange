<?php
/**
 * Orange Framework validation rule
 *
 * This content is released under the MIT License (MIT)
 *
 * @package	CodeIgniter / Orange
 * @author	Don Myers
 * @license http://opensource.org/licenses/MIT MIT License
 * @link	https://github.com/ProjectOrangeBox
 *
 */
class Validate_regex_match extends Validate_base {
	public function validate(&$field, $options) {
		$this->error_string = '%s is not in the correct format.';

		return (bool) preg_match($options, $field);
	}
} /* end class */