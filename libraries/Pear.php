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
 */

class Pear {
	protected static $helper_loaded = false;
	protected static $attached = [];
	protected static $loaded_plugin = [];

	protected static $extends  = null;
	protected static $fragment = null;

	/**
	 * Static function to capture static method calls
	 * @private
	 * @author Don Myers
	 * @param  string $name the name of the attached function to call
	 * @param  array $arguments the arguments to pass to the function
	 * @return string plugin output
	 */
	public static function __callStatic($name,$arguments) {
		if (!self::$helper_loaded) {
			ci()->load->helper(['html','form','date','inflector','language','number','text']);

			self::$helper_loaded = true;
		}

		/* Try to load the plugin if it's there */
		if (!self::$loaded_plugin[$name]) {
			ci()->load->pear_plugin($name);
		
			self::$loaded_plugin[$name] = true;
		}

		/* has it been attach? */
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

		throw new Exception('Plugin missing "'.$name.'"');
	}

	/**
	 * attach a closure as a plugin function
	 * @author Don Myers
	 * @param string $name name of the plugin function
	 * @param closure $closure function to call
	 */
	public static function attach($name,$closure) {
		self::$attached[$name] = $closure;
	}

	/**
	 * section function.
	 *
	 * @access public
	 * @static
	 * @param mixed $name
	 * @return void
	 */
	public static function section($name,$value=null) {
		if ($value) {
			ci()->load->vars([$name => $value]);
		} else {
			self::$fragment[$name] = $name;

			ob_start();
		}
	}

	/**
	 * parent function.
	 *
	 * @access public
	 * @static
	 * @param mixed $name (default: null)
	 * @return void
	 */
	public static function parent($name=null) {
		$name = ($name) ? $name : end(self::$fragment);

		echo ci()->load->get_var($name);
	}

	/**
	 * end function.
	 *
	 * @access public
	 * @static
	 * @return void
	 */
	public static function end() {
		$name = array_pop(self::$fragment);

		$buffer = ob_get_contents();

		ob_end_clean();

		ci()->load->vars([$name => $buffer]);
	}

	/**
	 * extends function.
	 *
	 * @access public
	 * @static
	 * @param mixed $name
	 * @return void
	 */
	public static function extends($name) {
		self::$extends = $name;
	}

	/**
	 * include function.
	 *
	 * @access public
	 * @static
	 * @param mixed $view (default: null)
	 * @param mixed $data (default: [])
	 * @param bool $name (default: true)
	 * @return void
	 */
	public static function include($view = null, $data = [], $name = true) {
		if ($name === true) {
			/* if name is true then we want to echo this */
			echo ci()->page->view($view, $data, $name);
		} else {
			/* if else put a variable */
			ci()->page->view($view, $data, $name);
		}
	}

	public static function is_extending() {
		return self::$extends;
	}

	public static function plugins($names='') {
		/* plugins always load first */
		ci()->page->prepend_asset();

		ci()->load->pear_plugin($names);

		ci()->page->prepend_asset(false);
	}
	
} /* end class */
