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
 * Controller Base Class
 *
 * middleware request & responds handling
 * Determining if the site is "open"
 * auto library, model, helper, model catalog, controller model loading
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2019
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version v2.0
 * @filesource
 *
 * @config application.site open boolean
 * @config application.site open boolean
 *
 * @uses # router - CodeIgniter Router
 * @uses # event - Orange event
 *
 */

class MY_Controller extends \CI_Controller
{
	/**
	 * Cache the controllers output in seconds
	 *
	 * @var int
	 */
	public $cache_page_for;

	/**
	 * Name of the controller
	 *
	 * @var string
	 */
	public $controller;

	/**
	 * URL to this controller
	 *
	 * @var string
	 */
	public $controller_path;

	/**
	 * Libraries to autoload
	 * available to all methods
	 *
	 * @var array
	 */
	public $libraries;

	/**
	 * Helpers to autoload
	 * available to all methods
	 *
	 * @var array
	 */
	public $helpers;

	/**
	 * Models to autoload
	 * available to all methods
	 *
	 * @var array
	 */
	public $models;

	/**
	 * Model catalogs to autoload
	 * available to all methods
	 *
	 * @var array
	 */
	public $catalogs;

	/**
	 * Universally accessible view data array
	 *
	 * @var array
	 */
	public $data = [];

	/**
	 * Name of the default controller model autoloaded
	 *
	 * @var string
	 */
	public $controller_model;

	public $user;

	/**
	 *
	 * Constructor
	 *
	 * @access public
	 *
	 */
	public function __construct()
	{
		/* let the parent controller do it's work */
		parent::__construct();

		log_message('debug', 'MY_Controller::__construct');

		/**
		 * Is the site up?
		 *
		 * If this is a cli request then it's always up
		 * If they have ISOPEN cookie matching application.is open cookie configuration value then continue processing
		 * Else Show the 503 server down error page
		 *
		 */
		if (php_sapi_name() !== 'cli') {
			if (!config('application.site open', true)) {
				if ($_COOKIE['ISOPEN'] !== config('application.is open cookie', md5(uniqid(true)))) {
					$this->errors->display(503, ['heading' => 'Please Stand By', 'message' => 'Site Down for Maintenance']);
				}
			}
		}

		$this->router->handle_requests($this);

		/* trigger our start up event */
		ci('event')->trigger('ci.controller.startup', $this);
	}

	/**
	 *
	 * Final output
	 *
	 * @access public
	 *
	 * @param $output
	 *
	 * @return void
	 *
	 */
	public function _output($output) : void
	{
		/**
		 * CodeIgniter sends in null if nothing has been sent for output
		 * lets normalize it
		 */
		$output = ($output) ?? '';

		echo $this->router->handle_responds($this, $output);

		/* trigger our output event */
		ci('event')->trigger('ci.controller.output', $this, $output);
	}

	/**
	 *
	 * Command Line Placeholder
	 *
	 * @access public
	 *
	 * @return mixed
	 *
	 */
	public function indexCliAction()
	{
		if (method_exists($this, 'helpCliAction')) {
			$this->helpCliAction();
		}
	}
} /* end class */
