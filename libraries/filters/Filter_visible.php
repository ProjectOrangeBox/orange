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
 * @help filter visible characters and optional length
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2019
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version v2.0
 *
 */

class Filter_visible extends \Filter_base
{
	public function filter(&$field, string $options = '') : void
	{
		$field = preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', $field);

		/* options is max length - filter is in orange core */
		$this->field($field)->length($options);
	}
} /* end class */
