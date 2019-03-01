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
 * Pear - view accessible functions
 *
 * Pear provides a way to abstract PHP functions into reusable packages
 * which can be easily called from a view without all the additional PHP in the actual view
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2019
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version v2.0
 *
 * @uses # html CodeIgniter html helper
 * @uses # form CodeIgniter form helper
 * @uses # date CodeIgniter date helper
 * @uses # inflector CodeIgniter inflector helper
 * @uses # language CodeIgniter language helper
 * @uses # number CodeIgniter number helper
 * @uses # text CodeIgniter text helper
 *
 */
class Pear
{
	/**
	 * Track if the helpers have been loaded yet.
	 *
	 * @var boolean
	 */
	protected static $helpers_loaded = false;

	/**
	 * Storage for the loaded plugins instances
	 *
	 * @var array
	 */
	protected static $loaded_plugins = [];

	/**
	 * unified place holder for pear fragments
	 * this is used by child plugins
	 *
	 * @var array
	 */
	public static $fragment = [];

	/**
	 *
	 * this is the static wrapper for loading and calling the actual plugins
	 *
	 * @static
	 * @access public
	 *
	 * @param string $name name of the plugin
	 * @param array $arguments arguments from the plugin call
	 *
	 * @throws \Exception
	 * @return mixed output from plugin
	 *
	 */
	public static function __callStatic(string $name, array $arguments = [])
	{
		log_message('debug', 'Pear::__callStatic::'.$name);

		/**
		 * Load as a class and save in loaded
		 * plugins for later use don't
		 * throw a error if it's not found
		 */
		self::plugin($name, false);

		/* Was this plugin loaded from the action above? */
		if (isset(self::$loaded_plugins[$name])) {
			if (method_exists(self::$loaded_plugins[$name], 'render')) {
				return call_user_func_array([self::$loaded_plugins[$name],'render'], $arguments);
			}
		}

		/* Are the CodeIgniter Helpers loaded? let's track this so we don't try over and over */
		if (!self::$helpers_loaded) {
			ci('load')->helper(['html','form','date','inflector','language','number','text']);

			self::$helpers_loaded = true;
		}

		/* Is this a CodeIgniter form_XXX function? */
		if (function_exists('form_'.$name)) {
			return call_user_func_array('form_'.$name, $arguments);
		}

		/* A PHP function or CodeIgniter html, date, inflector, language, number, text function */
		if (function_exists($name)) {
			return call_user_func_array($name, $arguments);
		}

		/* beats me */
		throw new \Exception('Plugin missing "'.$name.'"');
	}

	/**
	 *
	 * Load a plugin
	 *
	 * @static
	 * @access public
	 *
	 * @param $name name of the pear plugin to load
	 * @param $throw_error whether to throw a error [true]
	 *
	 * @throws \Exception
	 * @return void
	 *
	 */
	public static function plugin(string $name, bool $throw_error=true) : void
	{
		if (!isset(self::$loaded_plugins[$name])) {
			$class_name = 'Pear_'.str_replace('pear_', '', strtolower($name));

			if (class_exists($class_name, true)) {
				self::$loaded_plugins[$name] = new $class_name;
			} elseif ($throw_error) {
				throw new \Exception('Could not load "'.$class_name.'"');
			}
		}
	}
} /* end class */
