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
class Validate_exact_length extends Validate_base {
	public function validate(&$field, $options) {
		$this->error_string = '%s must be exactly %s characters in length.';

		if (!is_numeric($options)) {
			return false;
		}

		return (MB_ENABLED === true) ? (mb_strlen($field) === (int) $options) : (strlen($field) === (int) $options);
	}
} /* end class */