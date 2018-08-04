<?php
/**
 * Filter_base
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
abstract class Filter_base extends Validate_base {
	public function __construct(&$field_data=null) {
		$this->field_data = &$field_data;

		log_message('info', 'Filter_base Class Initialized');
	}

	/**
	 * filter
	 * Insert description here
	 *
	 * @param $field
	 * @param $options
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function filter(&$field, $options) {}

} /* end class */
