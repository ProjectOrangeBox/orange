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
class Validate_integer extends Validate_base {
	public function validate(&$field, $options) {
		$this->error_string = '%s is not a integer.';

		return (bool) preg_match('/^[\-+]?[0-9]+$/', $field);
	}
} /* end class */