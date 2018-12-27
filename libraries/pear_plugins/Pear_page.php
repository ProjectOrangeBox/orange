<?php

// @help get page variable. This allows for further processing before display.

class Pear_page extends Pear_plugin {

	public function render($name=null) {
		return ci('page')->var($name);
	}

} /* end plugin */