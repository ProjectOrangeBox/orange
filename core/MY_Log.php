<?php
/*
 * Orange Framework Extension
 *
 * @package	CodeIgniter / Orange
 * @author Don Myers
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 *
 * required
 * core:
 * libraries:
 * models:
 * helpers:
 * functions:
 *
 */

class MY_Log extends CI_Log {

	public function __construct() {
		parent::__construct();

		$config = get_config();

		/* make bailing even faster */
		if ($config['log_threshold'] == 0) {
			$this->_enabled = FALSE;
		}
	}

} /* End of Class */
