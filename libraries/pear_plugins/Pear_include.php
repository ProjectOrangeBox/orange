<?php 

// @help Include another view file with optional data and the ability to capture into a variable.

class Pear_include extends Pear_plugin {

	public function render($view=null,$data=[],$name=true) {
		if ($name === true) {
			echo ci('page')->view($view, $data, $name);
		} else {
			ci('page')->view($view, $data, $name);
		}
	}

} /* end plugin */