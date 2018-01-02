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
	public function request($index = null, $default = null, $xss_clean = false) {
		$value = (count($_POST)) ? $this->post($index, $xss_clean) : $this->input_stream($index, $xss_clean);

		return ($value === null) ? $default : $value;
	}

	public function request_replace($index = null, $replace_value = null) {
		if (count($_POST)) {
			$_POST[$index] = $replace_value;
		} else {
			$this->_input_stream[$index] = $replace_value;
		}
	}

	public function remap($map = [],$keep_current = false) {
		$mapped_form_data = [];

		foreach ($map as $new_key => $old_key) {
			$mapped_form_data[$new_key] = $this->request($old_key,null);
		}

		$new_data = ($keep_current) ? array_merge((array)$this->request(),$mapped_form_data) : $mapped_form_data;

		if (count($_POST)) {
			$_POST = $new_data;
		} else {
			$this->_input_stream = $new_data;
		}

		return $this;
	}

	public function cookie($index = null, $default = null, $xss_clean = null) {
		$cookie = $this->_fetch_from_array($_COOKIE, $index, $xss_clean);

		return ($cookie) ? $cookie : $default;
	}
} /* end file */