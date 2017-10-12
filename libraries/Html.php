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
 * This is a big static wrapper around numours helpers
 *
 */

class Html {
	public static $helper_loaded = false;
	public static $attached = [];

	public static function __callStatic($name,$arguments) {
		if (!self::$helper_loaded) {
			ci()->load->helper(['html','form','date','inflector','language','number','text']);

			self::$helper_loaded = true;
		}
		
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
		
		/* Plugin_????? = html::?????() */
		if (page::plugin($name)) {
			if (isset(self::$attached[$name])) {
				return call_user_func_array(self::$attached[$name],$arguments);
			}
		}
	
		throw new Exception('HTML helper missing "html_'.$name.'"');
	}

	public static function attach($name,$closure) {
		self::$attached[$name] = $closure;
	}

} /* end class */