<?php
/**
 * Orange
 *
 * An open source extensions for CodeIgniter 3.x
 *
 * This content is released under the MIT License (MIT)
 * Copyright (c) 2014 - 2019, Project Orange Box
 */

/**
 * Validation Filter
 *
 * @help clean for use as human text optional length
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2019
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version v2.0
 * @filesource
 *
 */
class Filter_human extends \Filter_base
{
	public function filter(&$field, string $options = '') : void
	{
		/*
		only word characters - from a-z, A-Z, 0-9, including the _ (underscore) character
		then trim any _ (underscore) characters from the beginning and end of the string
		convert to lowercase
		replace _ (underscore) characters with spaces
		uppercase words
		*/
		$field = ucwords(str_replace('_', ' ', strtolower(trim(preg_replace('#\W+#', ' ', $field), ' '))));

		/* options is max length */
		$this->field($field)->length($options);
	}
} /* end class */
