<?php
/**
 * Validate_valid_ip
 * Insert description here
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2018
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version 2.0
 *
 * required
 * core:
 * libraries:
 * models:
 * helpers:
 * functions:
 *
 */
class Validate_valid_ip extends Validate_base {
	public function validate(&$field, $options) {
		$this->error_string = '%s must contain a valid IP.';
		$options = (!empty($options)) ? $options : 'ipv4';
		return ci()->input->valid_ip($field, $options);
	}
}
