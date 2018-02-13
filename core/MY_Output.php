<?php
/**
 * MY_Output
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
class MY_Output extends CI_Output {
	public function json($data = null, $val = null) {
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

/**
 * nocache
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
	public function nocache() {
		$this
			->set_header('Expires: Sat,26 Jul 1997 05:00:00 GMT')
			->set_header('Cache-Control: no-cache,no-store,must-revalidate,max-age=0')
			->set_header('Cache-Control: post-check=0,pre-check=0', false)
			->set_header('Pragma: no-cache');
		return $this;
	}

/**
 * set_cookie
 * Insert description here
 *
 * @param $name
 * @param $value
 * @param $expire
 * @param $domain
 * @param $path
 * @param $prefix
 * @param $secure
 * @param $httponly
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function set_cookie($name = '', $value = '', $expire = '', $domain = '', $path = '/', $prefix = '', $secure = FALSE, $httponly = FALSE) {
		return ci()->input->set_cookie($name, $value, $expire, $domain, $path, $prefix, $secure, $httponly);
	}

/**
 * delete_all_cookies
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
	public function delete_all_cookies() {
		foreach ($_COOKIE as $key=>$value) {
	    setcookie($key,$value,(time() - 3600),config('config.cookie_path','/'));
		}
	}
}
