<?php
/**
 * MY_Loader
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
class MY_Loader extends CI_Loader {
	/**
	 * track if the combined cached configuration has been loaded
	 *
	 * @var boolean
	 */
	protected $cache_drivers_loaded = false;

	/**
	 * Internal Load extended function
	 */
	protected function _ci_init_library($class, $prefix, $config = FALSE, $object_name = NULL) {
		if (!$this->cache_drivers_loaded) {
			$this->cache_drivers_loaded = true;
			$cache_config = get_config();
			ci()->load->driver('cache', ['adapter' => $cache_config['cache_default'], 'backup' => $cache_config['cache_backup']]);
			/* attach page and export to CodeIgniter cache singleton loaded above */
			$CI = &get_instance();
			$CI->cache->page = new Cache_page($cache_config);
			$CI->cache->export = new Cache_export($cache_config);
		}

		if ($config == FALSE) {
			$paths = orange_paths('configs');
			$lc_class = strtolower($class);
			$config = [];
			if (isset($paths['root'][$lc_class])) {
				include $paths['root'][$lc_class];
			}
			if (isset($paths[ENVIRONMENT][$lc_class])) {
				include $paths[ENVIRONMENT][$lc_class];
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
	 * Internal Load extended function
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

		$paths = orange_paths('classes');

		$lc_library_name = strtolower($library_name);

		if (isset($paths[$prefix.$lc_library_name])) {
			include_once $paths[$prefix.$lc_library_name];
			if (class_exists($prefix.$lc_library_name, FALSE)) {
				return $this->_ci_init_library($library_name, $prefix, $params, $object_name);
			} else {
				log_message('debug', $path.' exists, but does not declare '.$prefix.$library_name);
			}
		}

		include_once BASEPATH.'libraries/'.$file_path.$library_name.'.php';

		$subclass = config_item('subclass_prefix');

		if (isset($paths[$subclass.$lc_library_name])) {
			include_once $paths[$subclass.$lc_library_name];
			if (class_exists($subclass.$lc_library_name, FALSE)) {
				$prefix = $subclass;
			} else {
				log_message('debug', $path.' exists, but does not declare '.$subclass);
			}
		}
		return $this->_ci_init_library($library_name, $prefix, $params, $object_name);
	}

} /* end class */
