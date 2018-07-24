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
 * @show clean for use as a filename
 */
class Filter_filename extends Filter_base {
	public function filter(&$field, $options) {
		/*
		only word characters - from a-z, A-Z, 0-9, including the _ (underscore) character
		then trim any _ (underscore) characters from the beginning and end of the string
		*/
		$field = strtolower(trim(preg_replace('#\W+#', '_', $field),'_'));

		/* options is max length - filter is in orange core */
		$this->field($field)->length($options);
	}
} /* end class */
