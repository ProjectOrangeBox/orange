<?php
/**
 * MY_Log
 * Insert description here
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2018
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version 2.0
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

		if ($config['log_threshold'] == 0) {
			$this->_enabled = FALSE;
		}
	}
} /* end class */
