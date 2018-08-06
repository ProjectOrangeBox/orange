<?php 

// @show Extend a base template.

class Pear_extends extends Pear_plugin {

	public function render($name=null,$data=[]) {
		ci('page')->data($data)->extend($name);
	}

} /* end plugin */