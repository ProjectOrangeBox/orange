<?php
/**
 * Pear Plugin
 * Orange View Plug Parent Library
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2018
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version 2.0
 *
 * required: none
 *
 */
class Pear_plugin {

	public function render() {}

	protected function _convert2attributes($attributes,$prefix='') {
		$output = '';
		foreach ($attributes as $name=>$value) {
			$output .= $prefix.$name.'="'.trim($value).'" ';
		}
		return trim($output);
	}

}
