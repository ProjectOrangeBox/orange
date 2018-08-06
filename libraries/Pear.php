<?php
/**
 * Pear
 * Orange View Plug library
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2018
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version 2.0
 *
 * required
 * core: load
 * libraries: page
 * models:
 * helpers: html, form, date, inflector, language, number, text
 * functions:
 *
 */
class Pear {
	/**
	 * track if the combined cached configuration has been loaded
	 *
	 * @var boolean
	 */
	protected static $helpers_loaded = false;

	/**
	 * track if the combined cached configuration has been loaded
	 *
	 * @var boolean
	 */
	protected static $loaded_plugins = [];

	/**
	 * track if the combined cached configuration has been loaded
	 *
	 * @var boolean
	 */
	public static $fragment = null;

	/**
	 * __callStatic
	 * Insert description here
	 *
	 * @param $name
	 * @param $arguments
	 *
	 * @return mixed - output from plugin
	 *
	 * @throws Plugin missing
	 */
	public static function __callStatic($name,$arguments) {
		log_message('debug', 'Pear::__callStatic::'.$name);

		/* Load as a class and save in attached classes for later use */
		self::plugin($name,false);

		/* Is there a method on the class we are requesting? */
		if (isset(self::$loaded_plugins[$name])) {
			if (method_exists(self::$loaded_plugins[$name],'render')) {
				return call_user_func_array([self::$loaded_plugins[$name],'render'],$arguments);
			}
		}

		if (!self::$helpers_loaded) {
			ci('load')->helper(['html','form','date','inflector','language','number','text']);

			self::$helpers_loaded = true;
		}

		/* A CodeIgniter form_XXX function */
		if (function_exists('form_'.$name)) {
			return call_user_func_array('form_'.$name,$arguments);
		}

		/* A PHP function or CodeIgniter html, date, inflector, language, number, text function */
		if (function_exists($name)) {
			return call_user_func_array($name,$arguments);
		}

		/* beats me */
		throw new Exception('Plugin missing "'.$name.'"');
	}

	/**
	 * Load Plugin
	 *
	 * @param $name string - name of the pear plugin to load
	 * @param $throw_error boolean - throw a error if the pear plugin isn't found
	 *
	 */
	public static function plugin($name,$throw_error=true) {
		if (!isset(self::$loaded_plugins[$name])) {
			$class_name = 'Pear_'.str_replace('pear_','',strtolower($name));

			if (class_exists($class_name,true)) {
				self::$loaded_plugins[$name] = new $class_name;
			} elseif ($throw_error) {
				throw new Exception('Could not load "'.$class_name.'"');
			}
		}
	}

} /* end class */