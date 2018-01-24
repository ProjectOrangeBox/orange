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

class MY_Input extends CI_Input {
	protected $_request;

	public function __construct() {
		parent::__construct();

		$this->_request = ($_POST) ? $_POST : $this->input_stream();

		log_message('info', 'MY_Input Class Initialized');
	}

	/* return input_stream with default - this includes POST, PUT, DELETE, as long as Content-Type is application/x-www-form-urlencoded */
	public function request($index = null, $default = null, $xss_clean = false) {
		log_message('debug', 'MY_Input::request::'.$index);

		$value = $this->_fetch_from_array($this->_request, $index, $xss_clean);

		return ($value === null) ? $default : $value;
	}

	/* replace a input stream value with another */
	public function request_replace($index = null, $replace_value = null) {
		$this->_request[$index] = $replace_value;
	}

	/* remap multiple input_stream values to different values with option to keep original values */
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

	/* return cookie with default */
	public function cookie($index = null, $default = null, $xss_clean = null) {
		$value = $this->_fetch_from_array($_COOKIE, $index, $xss_clean);

		return ($value === null) ? $default : $value;
	}
} /* end file */
