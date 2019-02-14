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
 * Session extensions to provide a remove feature after requesting a value
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2019
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version v2.0
 *
 * @uses # session - CodeIgniter Session
 *
 *
 */
class Session extends CI_Session
{
	
	/**
	 *
	 * Wrapper for session userdata reading with new optional remove option
	 * @note no type hinting because parent class does not use type hinting
	 *
	 * @access public
	 *
	 * @param string $key NULL
	 * @param bool $remove false
	 *
	 * @return mixed
	 *
	 * #### Example
	 * ```php
	 * $name = ci('session')->userdata('name');
	 * $name = ci('session')->userdata('name',true);
	 * ```
	 */
	public function userdata($key = null, $remove = false)
	{
		$data = parent::userdata($key);

		if (is_string($key) && $remove) {
			$this->unset_userdata($key);
		}

		return $data;
	}

	/**
	 *
	 * Wrapper for session tempdata reading with new optional remove option before expiration time
	 * @note no type hinting because parent class does not use type hinting
	 *
	 * @access public
	 *
	 * @param string $key NULL
	 * @param bool $remove false
	 *
	 * @return mixed
	 *
	 * #### Example
	 * ```php
	 * $name = ci('session')->tempdata('name');
	 * $name = ci('session')->tempdata('name',true);
	 * ```
	 */
	public function tempdata($key = null, $remove = false)
	{
		$data = parent::tempdata($key);

		if (is_string($key) && $remove) {
			$this->unset_tempdata($key);
		}

		return $data;
	}
} /* end class */
