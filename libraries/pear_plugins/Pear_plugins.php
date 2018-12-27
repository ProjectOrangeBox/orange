<?php 

// @help load pear plugin(s).

class Pear_plugins extends Pear_plugin {

	public function render($input=null) {
		/* convert this to a array */
		$plugins = (strpos($input,',') !== false) ? explode(',',$input) : (array)$input;

		/* load the plug in and throw a error if it's not found */
		foreach ($plugins as $plugin) {
			pear::plugin($plugin);
		}
	}

} /* end plugin */