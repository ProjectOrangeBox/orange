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
	protected static $setup = false;

	/**
	 * track if the combined cached configuration has been loaded
	 *
	 * @var boolean
	 */
	protected static $loaded = [];

	/**
	 * track if the combined cached configuration has been loaded
	 *
	 * @var boolean
	 */
	protected static $fragment = null;
	

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
		self::load_plugin($name);

		/* Is there a method on the class we are requesting? */
		if (isset(self::$loaded[$name])) {
			if (method_exists(self::$loaded[$name],'render')) {
				return call_user_func_array([self::$loaded[$name],'render'],$arguments);
			}
		}

		/* Did we load the CodeIgniter helpers */
		if (!self::$setup) {
			ci('load')->helper(['html','form','date','inflector','language','number','text']);

			self::$setup = true;
		}

		/* A CodeIgniter form_XXX function */
		if (function_exists('form_'.$name)) {
			return call_user_func_array('form_'.$name,$arguments);
		}

		/* A CodeIgniter html, date, inflector, language, number, text function */
		if (function_exists($name)) {
			return call_user_func_array($name,$arguments);
		}

		/* beats me */
		throw new Exception('Plugin missing "'.$name.'"');
	}

	/**
	 * section
	 * Insert description here
	 *
	 * @param $name
	 * @param $value
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public static function section($name,$value=null) {
		if ($value) {
			ci('load')->vars([$name => $value]);
		} else {
			self::$fragment[$name] = $name;
			ob_start();
		}
		
		return ci('load')->get_var($name);
	}

	/**
	 * parent
	 * Insert description here
	 *
	 * @param $name
	 *
	 * @return
	 *
	 */
	public static function parent($name=null) {
		$name = ($name) ? $name : end(self::$fragment);

		echo ci('load')->get_var($name);
	}

	/**
	 * end
	 * Insert description here
	 *
	 *
	 * @return
	 *
	 */
	public static function end() {
		if (!count(self::$fragment)) {
			throw new Exception('Cannot end section because you are not in a section.');
		}

		$name = array_pop(self::$fragment);
		$buffer = ob_get_contents();
		ob_end_clean();

		ci('load')->vars([$name => $buffer]);
	}

	/**
	 * extends
	 * Insert description here
	 *
	 * @param $name
	 *
	 * @return
	 *
	 */
	public static function extends($name,$data=[]) {
		ci('load')->vars($data);

		ci('page')->extend($name);
	}

	/**
	 * include
	 * Insert description here
	 *
	 * @param $view
	 * @param $data
	 * @param $name
	 *
	 * @return
	 *
	 */
	public static function include($view = null, $data = [], $name = true) {
		if ($name === true) {
			echo ci('page')->view($view, $data, $name);
		} else {
			ci('page')->view($view, $data, $name);
		}
	}

	/**
	 * load plugins
	 *
	 * @param $name - mixed string, comma separated plugin names, array
	 *
	 */
	public static function plugins($name) {
		/* convert this to a array */
		$plugins = (strpos($name,',') !== false) ? explode(',',$name) : (array)$name;

		/* load the plug in and throw a error if it's not found */
		foreach ($plugins as $plugin) {
			self::load_plugin($plugin,true);
		}
	}

	public static function plugin($name) {
		self::load_plugin($name,true);
	}

	/**
	 * Load Plugin
	 *
	 * @param $name string - name of the pear plugin to load
	 * @param $throw_error boolean - throw a error if the pear plugin isn't found
	 *
	 */
	protected static function load_plugin($name,$throw_error=false) {
		$class_name = 'Pear_'.str_replace('pear_','',strtolower($name));

		if (!isset(self::$loaded[$name])) {
			if (class_exists($class_name,true)) {
				self::$loaded[$name] = new $class_name;
			} elseif ($throw_error) {
				throw new Exception('Could not load "'.$class_name.'"');
			}
		}
	}

} /* end class */
