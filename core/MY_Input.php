<?php
/**
 * MY_Input
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
class MY_Input extends CI_Input {
	protected $_request;

/**
 * __construct
 * Insert description here
 *
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function __construct() {
		parent::__construct();
		$this->_request = ($_POST) ? $_POST : $this->input_stream();
		log_message('info', 'MY_Input Class Initialized');
	}

/**
 * request
 * Insert description here
 *
 * @param $index
 * @param $default
 * @param $xss_clean
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function request($index = null, $default = null, $xss_clean = false) {
		log_message('debug', 'MY_Input::request::'.$index);
		$value = $this->_fetch_from_array($this->_request, $index, $xss_clean);
		return ($value === null) ? $default : $value;
	}

/**
 * request_replace
 * Insert description here
 *
 * @param $index
 * @param $replace_value
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function request_replace($index = null, $replace_value = null) {
		$this->_request[$index] = $replace_value;
	}

/**
 * request_remap
 * Insert description here
 *
 * @param $map
 * @param $keep_current
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function request_remap($map = [],$keep_current = false) {
		$current_request = $this->_request;
		if (!$keep_current) {
			$this->_request = [];
		}
		foreach ($map as $new_key => $old_key) {
			$this->_request[$new_key] = $current_request[$old_key];
		}
		return $this;
	}

/**
 * cookie
 * Insert description here
 *
 * @param $index
 * @param $default
 * @param $xss_clean
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function cookie($index = null, $default = null, $xss_clean = null) {
		$value = $this->_fetch_from_array($_COOKIE, $index, $xss_clean);
		return ($value === null) ? $default : $value;
	}
}
