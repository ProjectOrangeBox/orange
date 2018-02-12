<?php
/*
 * Orange Framework Extension
 *
 * @package	CodeIgniter / Orange
 * @author Don Myers
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
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
		$this->field_data   = &$field_data;

		log_message('info', 'Filter_base Class Initialized');
	}

	public function filter(&$field, $options) {}

} /* end file */
