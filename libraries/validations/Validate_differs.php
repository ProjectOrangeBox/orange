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
class Validate_differs extends Validate_base {
	/* differs[emailer] */
	public function validate(&$field, $options) {
		$this->error_string = '%s must differ from %s.';

		return !($this->field_data[$field] === $this->field_data[$options];
		}
	} /* end class */