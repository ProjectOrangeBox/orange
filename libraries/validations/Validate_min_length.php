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
class Validate_min_length extends Validate_base {
	public function validate(&$field, $options) {
		$this->error_string = '%s must be at least %s characters in length.';

		if (!is_numeric($options)) {
			return false;
		}

		return (MB_ENABLED === TRUE) ? ($options <= mb_strlen($field)) : ($options <= strlen($field));
	}
} /* end class */