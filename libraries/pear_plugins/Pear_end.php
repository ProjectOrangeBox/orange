<?php 

// @show end a section.

class Pear_end extends Pear_plugin {

	public function render() {
		if (!count(pear::$fragment)) {
			throw new Exception('Cannot end section because you are not in a section.');
		}

		$name = array_pop(pear::$fragment);
		$buffer = ob_get_contents();
		ob_end_clean();

		ci('page')->data($name,$buffer);
	}

} /* end plugin */