<?php 

// @show start a page section.

class Pear_section extends Pear_plugin {

	public function render($name=null,$value=null) {
		if ($value) {
			ci('page')->data($name,$value);
		} else {
			pear::$fragment[$name] = $name;
			ob_start();
		}

		return ci('load')->get_var($name);
	}

} /* end plugin */