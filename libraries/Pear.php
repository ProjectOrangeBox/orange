<?php
/*
 * Orange Framework Extension
 *
 * @package	CodeIgniter / Orange
 * @author Don Myers
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
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
	protected static $setup = false;
	protected static $attached = [];
	protected static $extends  = null;
	protected static $fragment = null;

	public static function __callStatic($name,$arguments) {
		log_message('debug', 'Pear::__callStatic::'.$name);

		if (!self::$setup) {
			ci('load')->helper(['html','form','date','inflector','language','number','text']);

			self::$setup = true;
		}

		self::class_exists($name);

		if (isset(self::$attached[$name])) {
			return call_user_func_array(self::$attached[$name],$arguments);
		}

		if (function_exists('form_'.$name)) {
			return call_user_func_array('form_'.$name,$arguments);
		}

		if (function_exists($name)) {
			return call_user_func_array($name,$arguments);
		}

		throw new Exception('Plugin missing "'.$name.'"');
	}

	public static function attach($name,$closure) {
		log_message('debug', 'Pear::attach::'.$name);

		self::$attached[$name] = $closure;
	}

	public static function section($name,$value=null) {
		if ($value) {
			ci('load')->vars([$name => $value]);
		} else {
			self::$fragment[$name] = $name;
			ob_start();
		}
	}

	public static function parent($name=null) {
		$name = ($name) ? $name : end(self::$fragment);

		echo ci('load')->get_var($name);
	}

	public static function end() {
		$name = array_pop(self::$fragment);
		$buffer = ob_get_contents();

		ob_end_clean();

		ci('load')->vars([$name => $buffer]);
	}

	public static function extends($name) {
		self::$extends = $name;
	}

	public static function include($view = null, $data = [], $name = true) {
		if ($name === true) {
			echo ci('page')->view($view, $data, $name);
		} else {
			ci('page')->view($view, $data, $name);
		}
	}

	public static function is_extending() {
		return self::$extends;
	}

	public static function plugins($name) {
		ci('page')->prepend_asset(true);

		$name = (strpos($name,',') !== false) ? explode(',',$name) : $name;

		if (is_array($name)) {
			foreach ($name as $n) {
				self::class_exists($n);
			}
		} else {
			self::class_exists($name);
		}

		ci('page')->prepend_asset(false);
	}

	protected static function class_exists($name) {
		$name = 'Pear_'.str_replace('pear_','',strtolower($name));

		class_exists($name);
	}

} /* end file */
