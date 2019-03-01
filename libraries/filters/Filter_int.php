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
 * @help filter for integer optional length
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

class Filter_int extends \Filter_base
{
	public function filter(&$field, string $options = '') : void
	{
		$pos = strpos($field, '.');
		
		if ($pos !== false) {
			$field = substr($field, 0, $pos);
		}
		
		$field  = preg_replace('/[^\-\+0-9]+/', '', $field);
		$prefix = ($field[0] == '-' || $field[0] == '+') ? $field[0] : '';
		$field  = $prefix.preg_replace('/[^0-9]+/', '', $field);
		$this->field($field)->length($options);
	}
}
