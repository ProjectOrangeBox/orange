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
 * @help filter to create a slug
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

class Filter_slug extends \Filter_base
{
	public function filter(&$field, string $options = '') : void
	{
		$field = preg_replace('~[^\pL\d]+~u', '-', $field);
		$field = iconv('utf-8', 'us-ascii//TRANSLIT', $field);
		$field = preg_replace('~[^-\w]+~', '', $field);
		$field = trim($field, '-');
		$field = preg_replace('~-+~', '-', $field);
		$field = strtolower($field);
	}
}
