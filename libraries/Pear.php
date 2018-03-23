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
	protected static $attached = [];

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
		if (!self::$setup) {
			ci('load')->helper(['html','form','date','inflector','language','number','text']);
			ci('event')->register('page.render.content',function(&$view_content,&$view,&$data) {
				if (pear::is_extending()) {
					$view_content = ci('page')->view(pear::is_extending());
				}
			});
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
		if (strpos($name,'_')) {
			self::class_exists(current(explode('_',$name)));
			if (isset(self::$attached[$name])) {
				return call_user_func_array(self::$attached[$name],$arguments);
			}		
		}
		throw new Exception('Plugin missing "'.$name.'"');
	}

/**
 * attach
 * Insert description here
 *
 * @param $name
 * @param $closure
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public static function attach($name,$closure) {
		log_message('debug', 'Pear::attach::'.$name);
		self::$attached[$name] = $closure;
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
				self::class_exists($n);
			}
		} else {
			self::class_exists($name);
		}
		ci('page')->prepend_asset(false);
	}

/**
 * class_exists
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
	protected static function class_exists($name) {
		$name = 'Pear_'.str_replace('pear_','',strtolower($name));
		class_exists($name);
	}
}
