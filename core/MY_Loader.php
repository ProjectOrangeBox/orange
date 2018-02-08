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
 * core:
 * libraries:
 * models:
 * helpers:
 * functions:
 *
 */

class MY_Loader extends CI_Loader {
	protected $cache_drivers_loaded = false;

	/**
	 * Internal CI Library Instantiator
	 *
	 * @used-by	CI_Loader::_ci_load_stock_library()
	 * @used-by	CI_Loader::_ci_load_library()
	 *
	 * @param	string		$class		Class name
	 * @param	string		$prefix		Class name prefix
	 * @param	array|null|bool	$config		Optional configuration to pass to the class constructor:
	 *						FALSE to skip;
	 *						NULL to search in config paths;
	 *						array containing configuration data
	 * @param	string		$object_name	Optional object name to assign to
	 * @return	void
	 *
	 * add stream_resolve_include_path which is faster
	 * and CodeIgniter cache loader since we use it for caching stuff before the controller is loaded
	 *
	 */
	protected function _ci_init_library($class, $prefix, $config = FALSE, $object_name = NULL) {
		if (!$this->cache_drivers_loaded) {
			$this->cache_drivers_loaded = true;

			$config = get_config();

			ci()->load->driver('cache', ['adapter' => $config['cache_default'], 'backup' => $config['cache_backup']]);

			/* manually attach our cache drivers */
			$CI = &get_instance();

			$CI->cache->page = new Cache_page();
			$CI->cache->export = new Cache_export();

			/* save memory because these are libraries CI attached them to the super object */
			unset($CI->cache_page);
			unset($CI->cache_export);
		}

		if ($config === NULL) {
			$found       = FALSE;
			$config_file = stream_resolve_include_path('config/'.strtolower($class).'.php');

			if ($config_file) {
				$found = true;
				include $config_file;
			} else {
				$config_file = stream_resolve_include_path('config/'.ENVIRONMENT.'/'.strtolower($class).'.php');
				if ($config_file) {
					$found = true;
					include $config_file;
				}
			}
		}

		$class_name = $prefix.$class;

		if (!class_exists($class_name, FALSE)) {
			log_message('error', 'Non-existent class: '.$class_name);

			throw new Exception('Non-existent class: '.$class_name);
		}

		if (empty($object_name)) {
			$object_name = strtolower($class);
			if (isset($this->_ci_varmap[$object_name])) {
				$object_name = $this->_ci_varmap[$object_name];
			}
		}

		$CI = &get_instance();

		if (isset($CI->$object_name)) {
			if ($CI->$object_name instanceof $class_name || PHPUNIT) {
				log_message('debug', $class_name." has already been instantiated as '".$object_name."'. Second attempt aborted.");
				return;
			}

			throw new Exception("Resource '".$object_name."' already exists and is not a ".$class_name." instance.");
		}

		$this->_ci_classes[$object_name] = $class;

		$CI->$object_name = isset($config) ? new $class_name($config) : new $class_name();
	}

	/**
	 * Internal CI Stock Library Loader
	 *
	 * @used-by	CI_Loader::_ci_load_library()
	 * @uses	CI_Loader::_ci_init_library()
	 *
	 * @param	string	$library_name	Library name to load
	 * @param	string	$file_path	Path to the library filename, relative to libraries/
	 * @param	mixed	$params		Optional parameters to pass to the class constructor
	 * @param	string	$object_name	Optional object name to assign to
	 * @return	void
	 *
	 * add stream_resolve_include_path which is faster
	 *
	 */
	protected function _ci_load_stock_library($library_name, $file_path, $params, $object_name) {
		$prefix = 'CI_';

		if (class_exists($prefix.$library_name, FALSE)) {
			if (class_exists(config_item('subclass_prefix').$library_name, FALSE)) {
				$prefix = config_item('subclass_prefix');
			}

			if ($object_name !== NULL) {
				$CI = &get_instance();

				if (!isset($CI->$object_name)) {
					return $this->_ci_init_library($library_name, $prefix, $params, $object_name);
				}
			}

			log_message('debug', $library_name.' class already loaded. Second attempt ignored.');

			return;
		}

		if ($path = stream_resolve_include_path('libraries/'.$file_path.$library_name.'.php')) {
			include_once $path;

			if (class_exists($prefix.$library_name, FALSE)) {
				return $this->_ci_init_library($library_name, $prefix, $params, $object_name);
			} else {
				log_message('debug', $path.' exists, but does not declare '.$prefix.$library_name);
			}
		}

		include_once BASEPATH.'libraries/'.$file_path.$library_name.'.php';

		$subclass = config_item('subclass_prefix').$library_name;

		if ($path = stream_resolve_include_path('libraries/'.$file_path.$subclass.'.php')) {
			include_once $path;

			if (class_exists($subclass, FALSE)) {
				$prefix = config_item('subclass_prefix');
			} else {
				log_message('debug', $path.' exists, but does not declare '.$subclass);
			}
		}

		return $this->_ci_init_library($library_name, $prefix, $params, $object_name);
	}

} /* end file */
