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
 * @help end a section started with pear::section()
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2019
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version v2.0
 *
 */

class Pear_end extends \Pear_plugin
{
	public function render()
	{
		if (!count(pear::$fragment)) {
			throw new \Exception('Cannot end section because you are not in a section.');
		}

		$name = array_pop(pear::$fragment);
		$buffer = ob_get_contents();
		ob_end_clean();

		ci('page')->data($name, $buffer);
	}
} /* end plugin */
