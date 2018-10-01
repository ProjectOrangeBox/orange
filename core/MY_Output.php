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
	protected $json_options = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE;

/**
 * json
 * Send a Json responds
 *
 * @return $this
 *
 */
	public function json($data = null, $val = null, $raw = false) {
		/* what the heck do we have here... */
		if ($raw && $data === null) {
			$json = $val;
		} elseif ($raw && $data !== null) {
			$json = '{"'.$data.'":'.$val.'}';
		} elseif  (is_array($data) || is_object($data)) {
			$json = json_encode($data,$this->json_options);
		} elseif (is_string($data) && $val === null) {
			$json = $data;
		} elseif ($data === null && $val === null) {
			$json = json_encode(ci()->load->get_vars(),$this->json_options);
		} else {
			$json = json_encode([$data => $val],$this->json_options);
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
 * Send a nocache header
 *
 * @return $this
 *
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
 * Wrapper for input's set cookie because it more of a "output" function
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
 * @return $this
 *
 */
	public function set_cookie($name = '', $value = '', $expire = '', $domain = '', $path = '/', $prefix = '', $secure = FALSE, $httponly = FALSE) {
		ci('input')->set_cookie($name, $value, $expire, $domain, $path, $prefix, $secure, $httponly);

		return $this;
	}

/**
 * delete_all_cookies
 * Delete all cookies (ie. set to a time in the past since which will make the browser ignore them
 *
 * @return $this
 *
 */
	public function delete_all_cookies() {
		foreach (ci('input')->cookie() as $name=>$value) {
			ci('input')->set_cookie($name, $value, (time() - 3600),config('config.base_url'));
		}

		return $this;
	}

/**
 * provide this to allow mocking
 */
	public function exit($code=1) {
		exit($code);
	}

} /* end class */
