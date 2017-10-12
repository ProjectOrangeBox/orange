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
class Validate_less_than extends Validate_base {
	public function validate(&$field, $options) {
		$this->error_string = '%s must contain a number less than %s.';

		if (!is_numeric($field)) {
			return false;
		}

		return is_numeric($field) ? ($field < $options) : false;
	}
} /* end class */