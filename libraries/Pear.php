<?php
/**
 * Pear
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
	protected static $extends  = null;

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
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public static function __callStatic($name,$arguments) {
		log_message('debug', 'Pear::__callStatic::'.$name);

		/* Load as a class and save in attached classes for later use */
		self::load_class($name);

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
	}

	/**
	 * parent
	 * Insert description here
	 *
	 * @param $name
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
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
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public static function end() {
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
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public static function extends($name) {
		if (self::$extends !== null) {
			throw new Exception('Pear Templating is already extending "'.self::$extends.'" therefore we cannot extend "'.$name.'"');
		}

		self::$extends = $name;
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
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public static function include($view = null, $data = [], $name = true) {
		if ($name === true) {
			echo ci('page')->view($view, $data, $name);
		} else {
			ci('page')->view($view, $data, $name);
		}
	}

	/**
	 * is_extending
	 * Insert description here
	 *
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public static function is_extending() {
		return self::$extends;
	}

	/**
	 * plugins
	 * Insert description here
	 *
	 * @param $name
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public static function plugins($name) {
		ci('page')->prepend_asset(true);

		$name = (strpos($name,',') !== false) ? explode(',',$name) : $name;

		if (is_array($name)) {
			foreach ($name as $n) {
				self::load_class($n,true);
			}
		} else {
			self::load_class($name,true);
		}

		ci('page')->prepend_asset(false);
	}

	/**
	 * load_class
	 * Insert description here
	 *
	 * @param $name
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	protected static function load_class($name,$throw_error=false) {
		$class_name = 'Pear_'.str_replace('pear_','',strtolower($name));

		if (class_exists($class_name,true)) {
			if (!isset(self::$loaded[$name])) {
				self::$loaded[$name] = new $class_name;
			}
		} elseif ($throw_error) {
			throw new Exception('Could not load "'.$class_name.'"');
		}

	}

} /* end class */