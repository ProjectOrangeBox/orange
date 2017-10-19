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
 * core:
 * libraries:
 * models:
 * helpers:
 *
 * Static Library
 *
 * This is a big static wrapper around numorus helpers
 *
 */

class Html {
	public static $helper_loaded = false;
	public static $attached = [];

	/**
	 * Static function to capture static method calls
	 * @private
	 * @author Don Myers
	 * @param  string $name the name of the attached function to call
	 * @param  array $arguments the arguments to pass to the function
	 * @return string html helper output
	 */
	public static function __callStatic($name,$arguments) {
		if (!self::$helper_loaded) {
			ci()->load->helper(['html','form','date','inflector','language','number','text']);

			self::$helper_loaded = true;
		}
		
		/* Try to load the plugin if it's there */
		ci()->load->plugin_exists($name,true);

		/* has it been attach or override a function */
		if (isset(self::$attached[$name])) {
			return call_user_func_array(self::$attached[$name],$arguments);
		}
		
		/* is it a CodeIgniter Form function */
		if (function_exists('form_'.$name)) {
			return call_user_func_array('form_'.$name,$arguments);
		}

		/* php function */
		if (function_exists($name)) {
			return call_user_func_array($name,$arguments);
		}
		
		throw new Exception('HTML helper missing "html_'.$name.'"');
	}

	/**
	 * attach a closure as a html function
	 * @author Don Myers
	 * @param string $name name of the html function
	 * @param closure $closure function to call
	 */
	public static function attach($name,$closure) {
		self::$attached[$name] = $closure;
	}

} /* end class */