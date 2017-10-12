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
class Filter_textarea extends Filter_base {
	public function filter(&$field, $options) {
		/* $field pass by ref,options is the length */
		$this->field($field)->human_plus()->length($options);
	}
} /* end class */