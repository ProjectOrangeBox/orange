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
 * Authorization class.
 *
 * Handles login, logout, refresh user data
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2019
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version v2.0
 * @filesource
 *
 */
abstract class Middleware_base
{
	/**
	 * reference to CodeIgniter Super Object
	 *
	 * @var Object
	 */
	protected $ci;

	/**
	 *
	 * Constructor
	 *
	 * @access public
	 *
	 * @param $ci
	 *
	 */
	public function __construct(&$ci)
	{
		$this->ci = &$ci;
	}

	/**
	 *
	 * Called on request before the controller method is called
	 * This method is overridden in the child class
	 *
	 * @access public
	 *
	 * @return void
	 *
	 */
	public function request() : void
	{
	}

	/**
	 *
	 * Called on responds after the controller method is called
	 * This method is overridden in the child class
	 *
	 * @access public
	 *
	 * @param string $output
	 *
	 * @return string
	 *
	 */
	public function responds(string $output = '') : string
	{
		return $output;
	}

	/**
	 *
	 * Magic Method to allow $this to work in middleware
	 *
	 * @access public
	 *
	 * @param string $name
	 *
	 * @return mixed
	 *
	 */
	public function __get(string $name)
	{
		return $this->ci->$name;
	}
} /* end class */
