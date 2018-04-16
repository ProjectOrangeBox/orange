<?php

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
