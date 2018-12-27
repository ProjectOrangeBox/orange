<?php 

// @help start a page section.

class Pear_section extends Pear_plugin {

	public function render($name=null) {
		pear::$fragment[$name] = $name;
		ob_start();
	}

} /* end plugin */