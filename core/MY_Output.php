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
 * core: input
 * libraries:
 * models:
 * helpers:
 *
 */

class MY_Output extends CI_Output {

	/**
	 * Output Json Responds
	 * New Function
	 *
	 * @author Don Myers
	 * @param		mixed		array of key/values pairs or key or already formatted json
	 * @param		mixed		if the first value is not a array this would be the value of the key value pair
	 * @return $this allow chaining
	 */
	public function json($data = null, $val = null) {
		log_message('debug', 'my_output::json');
		
		if ($data === null) {
			$json = json_encode(ci()->load->get_vars());
		} else {
			$data = ($val !== NULL) ? [$data => $val] : $data;
	
			$json = (is_array($data) || is_object($data)) ? json_encode($data) : $data;
		}

		$this
			->enable_profiler(false)
			->nocache()
			->set_content_type('application/json', 'utf-8')
			->set_output($json);

		/* allow chaining */
		return $this;
	}

	/**
	 * Send No Cache Headers
	 * New Function
	 *
	 * @author Don Myers
	 * @return $this allow chaining
	 */
	public function nocache() {
		log_message('debug', 'my_output::nocache');

		$this->set_header('Expires: Sat,26 Jul 1997 05:00:00 GMT');
		$this->set_header('Cache-Control: no-cache,no-store,must-revalidate,max-age=0');
		$this->set_header('Cache-Control: post-check=0,pre-check=0', false);
		$this->set_header('Pragma: no-cache');

		/* allow chaining */
		return $this;
	}

	/**
	 * set_cookie function.
	 * 
	 * Wrapper for input's set_cookie
	 * 
	 * @author Don Myers
	 * @access public
	 * @param string $name (default: '')
	 * @param string $value (default: '')
	 * @param string $expire (default: '')
	 * @param string $domain (default: '')
	 * @param string $path (default: '/')
	 * @param string $prefix (default: '')
	 * @param mixed $secure (default: FALSE)
	 * @param mixed $httponly (default: FALSE)
	 * @return void
	 */
	public function set_cookie($name = '', $value = '', $expire = '', $domain = '', $path = '/', $prefix = '', $secure = FALSE, $httponly = FALSE) {
		return ci()->input->set_cookie($name, $value, $expire, $domain, $path, $prefix, $secure, $httponly);
	}

} /* end MY_Output */