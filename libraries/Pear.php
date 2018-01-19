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
	protected static $known_plugins = null;

	public static function __callStatic($name,$arguments) {
		log_message('debug', 'Pear::__callStatic::'.$name);

		if (!self::$setup) {
			ci('load')->helper(['html','form','date','inflector','language','number','text']);

			self::$known_plugins = cache_var_export::get('pear');

			if (!is_array(self::$known_plugins)) {
				self::$known_plugins = self::_build_cache('pear');
			}

			self::$setup = true;
		}

		self::_loader($name);

		if (isset(self::$attached[$name])) {
			return call_user_func_array(self::$attached[$name],$arguments);
		}

		if (function_exists('form_'.$name)) {
			return call_user_func_array('form_'.$name,$arguments);
		}

		if (function_exists($name)) {
			return call_user_func_array($name,$arguments);
		}

		/* let's rebuild the cache and try 1 more time */
		self::$known_plugins = self::_build_cache('pear');

		self::_loader($name);

		throw new Exception('Plugin missing "'.$name.'"');
	}

	public static function attach($name,$closure) {
		log_message('debug', 'Pear::attach::'.$name);

		self::$attached[$name] = $closure;
	}

	public static function section($name,$value=null) {
		log_message('debug', 'Pear::section::'.$name);

		if ($value) {
			ci('load')->vars([$name => $value]);
		} else {
			self::$fragment[$name] = $name;
			ob_start();
		}
	}

	public static function parent($name=null) {
		log_message('debug', 'Pear::parent::'.$name);

		$name = ($name) ? $name : end(self::$fragment);

		echo ci('load')->get_var($name);
	}

	public static function end() {
		log_message('debug', 'Pear::end');

		$name = array_pop(self::$fragment);
		$buffer = ob_get_contents();

		ob_end_clean();

		ci('load')->vars([$name => $buffer]);
	}

	public static function extends($name) {
		log_message('debug', 'Pear::extends::'.$name);

		self::$extends = $name;
	}

	public static function include($view = null, $data = [], $name = true) {
		log_message('debug', 'Pear::include::'.$view);

		if ($name === true) {
			echo ci('page')->view($view, $data, $name);
		} else {
			ci('page')->view($view, $data, $name);
		}
	}

	public static function is_extending() {
		log_message('debug', 'Pear::is_extending');

		return self::$extends;
	}

	public static function plugins($names) {
		ci('page')->prepend_asset(true);

		if (strpos($names,',') !== false) {
			foreach (explode(',',$names) as $name) {
				self::_loader($name);
			}
		} else {
			self::_loader($names);
		}

		ci('page')->prepend_asset(false);
	}

	protected static function _loader($name='') {
		$class = 'Pear_'.str_replace('pear_','',strtolower($name));

		log_message('debug', 'Pear::load::'.$class);

		if (!class_exists($class,false)) {
			if ($path = self::$known_plugins[$class]) {
				include $path;

				new $class;
			}
		}
	}

	protected static function _build_cache($name) {
		$a = explode(PATH_SEPARATOR,get_include_path());

		foreach ($a as $p) {
			$fs = glob($p.'/libraries/pear_plugins/*.php',GLOB_NOSORT);
			
			foreach($fs as $file) {
				$pathinfo = pathinfo($file);
			
				if (substr($pathinfo['filename'],0,5) == 'Pear_') {
			  	$known_plugins[$pathinfo['filename']] = $file;
				}
			}
		}

		cache_var_export::save($name,$known_plugins,3600);

		return $known_plugins;
	}

} /* end file */
