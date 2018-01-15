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

class MY_Output extends CI_Output {

	/*
	output json with the correct headers

	->json('name',$value) single value
	->json($key_value_array) multiple values

	*/
	public function json($data = null, $val = null) {
		log_message('debug', 'MY_Output::json');

		if ($data === null) {
			$json = json_encode(ci()->load->get_vars(),JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
		} else {
			$data = ($val !== NULL) ? [$data => $val] : $data;
			$json = (is_array($data) || is_object($data)) ? json_encode($data,JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) : $data;
		}

		$this
			->enable_profiler(false)
			->nocache()
			->set_content_type('application/json', 'utf-8')
			->set_output($json);

		return $this;
	}

	/* send a no cache header */
	public function nocache() {
		log_message('debug', 'MY_Output::nocache');

		$this->set_header('Expires: Sat,26 Jul 1997 05:00:00 GMT');
		$this->set_header('Cache-Control: no-cache,no-store,must-revalidate,max-age=0');
		$this->set_header('Cache-Control: post-check=0,pre-check=0', false);
		$this->set_header('Pragma: no-cache');

		return $this;
	}

	/* wrapper for input method set_cookie

	https://www.codeigniter.com/user_guide/helpers/cookie_helper.html?highlight=set_cookie#set_cookie
	*/
	public function set_cookie($name = '', $value = '', $expire = '', $domain = '', $path = '/', $prefix = '', $secure = FALSE, $httponly = FALSE) {
		log_message('debug', 'MY_Output::set_cookie::'.$name);

		return ci()->input->set_cookie($name, $value, $expire, $domain, $path, $prefix, $secure, $httponly);
	}

} /* end file */
