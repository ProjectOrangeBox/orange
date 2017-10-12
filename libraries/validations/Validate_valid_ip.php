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
class Validate_valid_ip extends Validate_base {
	public function validate(&$field, $options) {
		$this->error_string = '%s must contain a valid IP.';

		$options = (!empty($options)) ? $options : 'ipv4';

		return ci()->input->valid_ip($field, $options);
	}
} /* end class */