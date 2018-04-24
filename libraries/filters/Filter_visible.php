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
class Filter_visible extends Filter_base {
	public function filter(&$field, $options) {
		$field = preg_replace('/[\x00-\x1F\x7F\xA0]/u','',$field);

		/* options is max length - filter is in orange core */
		$this->field($field)->length($options);
	}
} /* end class */
