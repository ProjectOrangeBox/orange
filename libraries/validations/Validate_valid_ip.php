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
 * @version v2.0
 *
 * required
 * core:
 * libraries:
 * models:
 * helpers:
 * functions:
 *
 * @help if the supplied IP address is valid. Accepts an optional parameter of ‘ipv4’ or ‘ipv6’ to specify an IP format.
 *
 */
class Validate_valid_ip extends \Validate_base
{
	public function validate(&$field, string $options = '') : bool
	{
		$this->error_string = '%s must contain a valid IP.';

		$options = (!empty($options)) ? $options : 'ipv4';

		return (bool)ci()->input->valid_ip($field, $options);
	}
}
