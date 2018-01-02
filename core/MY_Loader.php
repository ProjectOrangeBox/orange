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

	protected function _ci_init_library($class, $prefix, $config = FALSE, $object_name = NULL) {
		if (!$this->cache_drivers_loaded) {
			$this->cache_drivers_loaded = true;

			ci()->load->driver('cache', ['adapter' => ci()->config->item('cache_default'), 'backup' => ci()->config->item('cache_backup')]);

			include __DIR__.'/../libraries/Cache_var_export.php';

			cache_var_export::init(ci()->config->config);
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