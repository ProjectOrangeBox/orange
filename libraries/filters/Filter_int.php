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
class Filter_int extends Filter_base {
	public function filter(&$field, $options) {
		$pos = strpos($field, '.');

		if ($pos !== FALSE) {
			$field = substr($field, 0, $pos);
		}

		$field  = preg_replace('/[^\-\+0-9]+/', '', $field);
		$prefix = ($field[0] == '-' || $field[0] == '+') ? $field[0] : '';
		$field  = $prefix . preg_replace('/[^0-9]+/', '', $field);

		/* options is max length */
		$this->field($field)->length($options);
	}
} /* end class */