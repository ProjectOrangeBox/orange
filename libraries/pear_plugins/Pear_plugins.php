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
 * @help load pear plugin(s).
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2019
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version v2.0
 *
 */

class Pear_plugins extends \Pear_plugin
{
	public function render(string $input = null)
	{
		/* convert this to a array */
		$plugins = (strpos($input, ',') !== false) ? explode(',', $input) : (array)$input;

		/* load the plug in and throw a error if it's not found */
		foreach ($plugins as $plugin) {
			pear::plugin($plugin);
		}
	}
} /* end plugin */
