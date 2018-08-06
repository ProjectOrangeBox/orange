<?php 

// @show load the parent view variable.

class Pear_parent extends Pear_plugin {

	public function render($name=null) {
		$name = ($name) ?? end(pear::$fragment);

		echo ci('load')->get_var($name);
	}

} /* end plugin */