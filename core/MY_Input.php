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
	/* return input_stream with default - this includes POST, PUT, DELETE, as long as Content-Type is application/x-www-form-urlencoded */
	public function request($index = null, $default = null, $xss_clean = false) {
		log_message('debug', 'MY_Input::request::'.$index);

		$value = $this->input_stream($index, $xss_clean);

		return ($value === null) ? $default : $value;
	}

	/* replace a input stream value with another */
	public function request_replace($index = null, $replace_value = null) {
		log_message('debug', 'MY_Input::request_replace::'.$index);

		$this->_input_stream[$index] = $replace_value;
	}

	/* remap multiple input_stream values to different values with option to keep original values */
	public function remap($map = [],$keep_current = false) {
		log_message('debug', 'MY_Input::remap');

		$new_form_data = [];

		foreach ($map as $new_key => $old_key) {
			$new_form_data[$new_key] = $this->input_stream($old_key);
		}

		$this->_input_stream = ($keep_current) ? array_merge((array)$this->_input_stream,$new_form_data) : $new_form_data;

		return $this;
	}

	/* return cookie with default */
	public function cookie($index = null, $default = null, $xss_clean = null) {
		log_message('debug', 'MY_Input::cookie::'.$index);

		$value = $this->_fetch_from_array($_COOKIE, $index, $xss_clean);

		return ($value === null) ? $default : $value;
	}
} /* end file */
