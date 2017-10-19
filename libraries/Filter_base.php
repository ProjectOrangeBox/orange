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
class Filter_base extends Validate_base {

	/**
	 * [[Description]]
	 * @private
	 * @author Don Myers
	 * @param array &$field_data all fields attached to this validation array
	 */
	public function __construct(&$field_data) {
		$this->field_data   = &$field_data;
	}
	
	/**
	 * wrapper
	 * @author Don Myers
	 * @param mixed &$field field need filtering
	 * @param string $options usually comma seperated list of options
	 */
	public function filter(&$field, $options) {}

} /* end class */