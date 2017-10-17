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
 * core: load, config
 * libraries: event, cache_var_export
 * models: o_setting_model, o_packages_model
 * helpers:
 *
 */

class MY_Loader extends CI_Loader {
	protected $cache_drivers_loaded = false;

	/**
	 * Internal CI Library Loader
	 *
	 * @used-by	CI_Loader::library()
	 * @uses CI_Loader::_ci_init_library()
	 *
	 * @author Don Myers
	 * @param	string	$class	Class name to load
	 * @param	mixed $params	Optional parameters to pass to the class constructor
	 * @param	string	$object_name	Optional object name to assign to
	 * @return void
	 *
	 * OVERRIDDEN FOR SPEED IMPROVEMENTS (get rid of looping) AND CACHE DRIVER LOADER
	 *
	 */
	protected function _ci_init_library($class, $prefix, $config = FALSE, $object_name = NULL) {
		/* load caching if it hasn't been already */
		if (!$this->cache_drivers_loaded) {
			/* throw the flag so this doesn't run again */
			$this->cache_drivers_loaded = true;

			/* load the application caching layer */
			ci()->load->driver('cache', ['adapter' => ci()->config->item('cache_default'), 'backup' => ci()->config->item('cache_backup')]);

			/* our orange custom var export php file cache library */
			include __DIR__.'/../libraries/Cache_var_export.php';
			
			cache_var_export::init(ci()->config->config);
		}

		/* let's do this only once */
		$lowercase_class = strtolower($class);

		if (!$this->orange_known($lowercase_class)) {
			// Is there an associated config file for this class? Note: these should always be lowercase
			if ($config === NULL) {
				$found       = FALSE;
				$config_file = stream_resolve_include_path('config/' . $lowercase_class . '.php');
	
				if ($config_file) {
					$found = true;
					include $config_file;
				} else {
					$config_file = stream_resolve_include_path('config/' . ENVIRONMENT . '/' . $lowercase_class . '.php');
	
					if ($config_file) {
						$found = true;
						include $config_file;
					}
				}
			}
	
			$class_name = $prefix . $class;
	
			// Is the class name valid?
			if (!class_exists($class_name, FALSE)) {
				log_message('error', 'Non-existent class: ' . $class_name);
				throw new Exception('Non-existent class: ' . $class_name);
			}
	
			// Set the variable name we will assign the class to
			// Was a custom class name supplied? If so we'll use it
			if (empty($object_name)) {
				$object_name = $lowercase_class;
				if (isset($this->_ci_varmap[$object_name])) {
					$object_name = $this->_ci_varmap[$object_name];
				}
			}
	
			// Don't overwrite existing properties
			$CI = &get_instance();
	
			if (isset($CI->$object_name)) {
	
				if ($CI->$object_name instanceof $class_name || PHPUNIT) {
					log_message('debug', $class_name . " has already been instantiated as '" . $object_name . "'. Second attempt aborted.");
					return;
				}
	
				throw new Exception("Resource '" . $object_name . "' already exists and is not a " . $class_name . " instance.");
			}
	
			// Save the class name and object name
			$this->_ci_classes[$object_name] = $class;

			// Instantiate the class
			$CI->$object_name = isset($config) ? new $class_name($config) : new $class_name();
		}
	}
	
	/* we already know about our stuff so no need for additional processing */
	protected function orange_known($lowercase_class) {
		$loaded = false;
		
		$known_orange_classes = ['errors','event','validate','wallet','html','auth','page','user'];
		$static = ['errors','event','html','user'];

		if (in_array($lowercase_class,$known_orange_classes)) {
			$loaded = true;

			$class = ucfirst($lowercase_class);
	
			// Save the class name and object name
			$this->_ci_classes[$lowercase_class] = $class;

			if (!in_array($lowercase_class,$static)) {
				$CI = &get_instance();

				// Instantiate the class
				$CI->$lowercase_class = new $class();
			}
		}
	
		return $loaded;
	}

	/**
	 * Internal CI Stock Library Loader
	 *
	 * @used-by	CI_Loader::_ci_load_library()
	 * @uses CI_Loader::_ci_init_library()
	 *
	 * @author Don Myers
	 * @param	string	$library_name Library name to load
	 * @param	string	$file_path	Path to the library filename, relative to libraries/
	 * @param	mixed $params		Optional parameters to pass to the class constructor
	 * @param	string	$object_name	Optional object name to assign to
	 * @return void
	 *
	 * OVERRIDDEN FOR SPEED IMPROVEMENTS (get rid of looping)
	 *
	 */
	protected function _ci_load_stock_library($library_name, $file_path, $params, $object_name) {
		$prefix = 'CI_';

		if (class_exists($prefix . $library_name, FALSE)) {
			if (class_exists(config_item('subclass_prefix') . $library_name, FALSE)) {
				$prefix = config_item('subclass_prefix');
			}

			// Before we deem this to be a duplicate request, let's see
			// if a custom object name is being supplied. If so, we'll
			// return a new instance of the object
			if ($object_name !== NULL) {
				$CI = &get_instance();
				if (!isset($CI->$object_name)) {
					return $this->_ci_init_library($library_name, $prefix, $params, $object_name);
				}
			}

			log_message('debug', $library_name . ' class already loaded. Second attempt ignored.');
			return;
		}

		if ($path = stream_resolve_include_path('libraries/' . $file_path . $library_name . '.php')) {
			// Override
			include_once $path;

			if (class_exists($prefix . $library_name, FALSE)) {
				return $this->_ci_init_library($library_name, $prefix, $params, $object_name);
			} else {
				log_message('debug', $path . ' exists, but does not declare ' . $prefix . $library_name);
			}
		}

		include_once BASEPATH . 'libraries/' . $file_path . $library_name . '.php';

		// Check for extensions
		$subclass = config_item('subclass_prefix') . $library_name;

		if ($path = stream_resolve_include_path('libraries/' . $file_path . $subclass . '.php')) {
			include_once $path;

			if (class_exists($subclass, FALSE)) {
				$prefix = config_item('subclass_prefix');
			} else {
				log_message('debug', $path . ' exists, but does not declare ' . $subclass);
			}
		}

		return $this->_ci_init_library($library_name, $prefix, $params, $object_name);
	}

	/**
	 * model_exists function.
	 * 
	 * @author Don Myers
	 * @access public
	 * @param mixed $resource
	 * @param bool $load (default: false)
	 * @return void
	 */
	public function model_exists($resource, $load = false) {
		return $this->_exists($resource, $load, 'models', 'model');
	}

	/**
	 * library_exists function.
	 * 
	 * @author Don Myers
	 * @access public
	 * @param mixed $resource
	 * @param bool $load (default: false)
	 * @return void
	 */
	public function library_exists($resource, $load = false) {
		return $this->_exists($resource, $load, 'libraries', 'library');
	}

	/**
	 * helper_exists function.
	 * 
	 * @access public
	 * @param mixed $resource
	 * @param bool $load (default: false)
	 * @return void
	 */
	public function helper_exists($resource, $load = false) {
		return $this->_exists($resource, $load, 'helpers', 'helper');
	}

	/**
	 * _exists function.
	 * 
	 * @author Don Myers
	 * @access protected
	 * @param mixed $resource
	 * @param mixed $load
	 * @param mixed $folder
	 * @param mixed $method
	 * @return void
	 */
	protected function _exists($resource, $load, $folder, $method) {
		log_message('debug', 'MY_Loader::_exists ' . $resource . ' ' . (string) $load . ' ' . $folder . ' ' . $method);

		$path_parts = pathinfo(trim($resource, '/'));

		$exists = stream_resolve_include_path($folder . '/' . $path_parts['dirname'] . '/' . ucfirst(strtolower($path_parts['filename'])) . '.php');

		if ($exists) {
			if ($load == 'load' || $load === true) {
				$this->$method($resource);
			} elseif ($load == 'include') {
				include_once $exists;
			}
		}

		return $exists;
	}

} /* end class */
