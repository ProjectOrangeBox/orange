<?php
/**
 * Orange
 *
 * An open source extensions for CodeIgniter 3.x
 *
 * This content is released under the MIT License (MIT)
 * Copyright (c) 2014 - 2019, Project Orange Box
 */

/**
 * Extension to CodeIgniter Output Class
 *
 * Provides automatic handling of
 * JSON output
 * nocache header
 * setting & deleting cookies
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2019
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version v2.0
 * @filesource
 *
 * @uses # input - CodeIgniter Input
 *
 * @config base_url
 *
 */

class MY_Output extends \CI_Output
{
	/**
	 * JSON encoding for all json output
	 *
	 * @var int
	 */
	protected $json_options = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE;

	/**
	 * Send a JSON responds
	 *
	 * @access public
	 *
	 * @param $data null
	 * @param $val null
	 * @param $raw false
	 *
	 * @return MY_Output
	 *
	 * #### Example
	 * ```php
	 * ci('output')->json('name','Johnny');
	 * ci('output')->json(['name'=>'Johnny']);
	 * ci('output')->json('{name:"Johnny"}',null,true);
	 * ci('output')->json(null,null,true); # use loader (view) variables
	 * ```
	 */
	public function json($data = null, $val = null, $raw = false) : MY_Output
	{
		/* what the heck do we have here... */
		if ($raw && $data === null) {
			$json = $val;
		} elseif ($raw && $data !== null) {
			$json = '{"'.$data.'":'.$val.'}';
		} elseif (is_array($data) || is_object($data)) {
			$json = json_encode($data, $this->json_options);
		} elseif (is_string($data) && $val === null) {
			$json = $data;
		} elseif ($data === null && $val === null) {
			$json = json_encode(ci()->load->get_vars(), $this->json_options);
		} else {
			$json = json_encode([$data => $val], $this->json_options);
		}

		$this
			->enable_profiler(false)
			->nocache()
			->set_content_type('application/json', 'utf-8')
			->set_output($json);

		return $this;
	}


	/**
	 *
	 * Send a nocache header
	 *
	 * @access public
	 *
	 * @return MY_Output
	 *
	 */
	public function nocache() : MY_Output
	{
		$this
			->set_header('Expires: Sat,26 Jul 1997 05:00:00 GMT')
			->set_header('Cache-Control: no-cache,no-store,must-revalidate,max-age=0')
			->set_header('Cache-Control: post-check=0,pre-check=0', false)
			->set_header('Pragma: no-cache');

		return $this;
	}

	/**
	 *
	 * Wrapper for input's set cookie because it more of a "output" function
	 *
	 * @access public
	 *
	 * @param $name
	 * @param string $value
	 * @param int $expire
	 * @param string $domain
	 * @param string $path /
	 * @param string $prefix
	 * @param bool $secure FALSE
	 * @param bool $httponly FALSE
	 *
	 * @return MY_Output
	 *
	 */
	public function set_cookie($name = '', string $value = '', int $expire = 0, string $domain = '', string $path = '/', string $prefix = '', bool $secure = false, bool $httponly = false) : MY_Output
	{
		ci('input')->set_cookie($name, $value, $expire, $domain, $path, $prefix, $secure, $httponly);

		return $this;
	}

	/**
	 *
	 * Delete all cookies (ie. set to a time in the past since which will make the browser ignore them
	 *
	 * @access public
	 *
	 * @return MY_Output
	 *
	 */
	public function delete_all_cookies() : MY_Output
	{
		foreach (ci('input')->cookie() as $name=>$value) {
			ci('input')->set_cookie($name, $value, (time() - 3600), config('config.base_url'));
		}

		return $this;
	}

	/**
	 *
	 * Provided to allow mocking to override and not exit
	 *
	 * @access public
	 *
	 * @param int $code 1
	 *
	 * @return void
	 *
	 */
	public function _exit(int $code = 1) : void
	{
		exit($code);
	}
} /* end class */
