<?php
/**
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
 * models
 * helpers:
 *
 */
class MY_Input extends CI_Input {
	/**
	 * Get input from request headers
	 *
	 * @author Don Myers
	 * @param	 string $index		 = null		 HTML form element name
	 * @param	 mixed	$default	 = null		 If the HTML form element is not avaiable return this default
	 * @param	 bool		$xss_clean = false	 Whether to apply XSS filtering
	 * @return string											 value of the HTML form element or default
	 */
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

	/**
	 * Remap form data to another key/value incase the form element names don't match the model column names
	 * $this->input->remap(['name'=>'fullname','age'=>'years'])->request('fullname');
	 *
	 * @author Don Myers
	 * @param	 array $map assocaitated array of key value pairs with optional default value
	 * @return object reference to MY_Input to allow futher chaining
	 */
	public function remap($map = [],$keep_current = false) {
		$mapped_form_data = [];

		foreach ($map as $new_key => $old_key) {
			$mapped_form_data[$new_key] = $this->request($old_key,null);
		}

		/* put it into $_POST so we can grab it from there using request */
		$_POST = ($keep_current) ? array_merge_recursive((array)$this->request(),$mapped_form_data) : $mapped_form_data;

		return $this;
	}

	public function cookie($index = null, $default = null, $xss_clean = null) {
		$cookie = $this->_fetch_from_array($_COOKIE, $index, $xss_clean);
		
		return ($cookie) ? $cookie : $default;
	}

} /* end MY_Input class */