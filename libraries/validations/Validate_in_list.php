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
class Validate_in_list extends Validate_base {
	public function validate(&$field, $options) {
		$this->error_string = '%s must contain one of the available selections.';

		// in_list[1,2,3,4]
		$types = ($options) ? $options : '';
		return (in_array($field, explode(',', $types)));
	}
} /* end class */