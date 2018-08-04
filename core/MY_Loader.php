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
	protected $remap = false;

	/**
	 * Internal Load extended function
	 */
	protected function _ci_init_library($class, $prefix, $config = FALSE, $object_name = NULL) {
		/* multiple exits */

		/* Get a instance of CodeIgniter Super Object */
		$CI = &get_instance();

		if (!$this->cache_drivers_loaded) {
			/* all of our caches are loaded */
			$this->cache_drivers_loaded = true;

			/* load config.php configuration file contents */
			$cache_config = get_config();

			/* attach cache driver now */
			$CI->load->driver('cache', ['adapter' => $cache_config['cache_default'], 'backup' => $cache_config['cache_backup']]);

			/* attach page and export to CodeIgniter cache singleton loaded above */
			$CI->cache->request = new Cache_request($cache_config); 
			$CI->cache->export = new Cache_export($cache_config);
		}

		$config = (!$config) ? config(strtolower($class),[]) : $config;

		$class_name = $prefix.$class;
		
		/* should this class name be remapped to another class? */
		if ($remap = $this->_remap($class_name)) {
			$object_name = strtolower($class_name);
			$class_name = $remap;
		}

		if (empty($object_name)) {
			$object_name = strtolower($class);
			if (isset($this->_ci_varmap[$object_name])) {
				$object_name = $this->_ci_varmap[$object_name];
			}
		}

		if (isset($CI->$object_name)) {
			if ($CI->$object_name instanceof $class_name || PHPUNIT) {
				log_message('debug', $class_name." has already been instantiated as '".$object_name."'. Second attempt aborted.");
				return;
			}

			throw new Exception("Resource '".$object_name."' already exists and is not a ".$class_name." instance.");
		}

		$this->_ci_classes[$object_name] = $class;

		$config = (is_array($config)) ? $config : [];

		$CI->$object_name = new $class_name($config,ci());
	}

	/**
	 * Internal Load extended function
	 */
	protected function _ci_load_stock_library($library_name, $file_path, $params, $object_name) {
		/* multiple exits */

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

		$orange_paths = orange_autoload_files::paths('classes');

		$lc_library_name = strtolower($library_name);

		if (isset($orange_paths[$prefix.$lc_library_name])) {
			include_once $orange_paths[$prefix.$lc_library_name];

			if (class_exists($prefix.$lc_library_name, FALSE)) {
				return $this->_ci_init_library($library_name, $prefix, $params, $object_name);
			} else {
				log_message('debug', $path.' exists, but does not declare '.$prefix.$library_name);
			}
		}

		include_once BASEPATH.'libraries/'.$file_path.$library_name.'.php';

		$subclass = config_item('subclass_prefix');

		if (isset($orange_paths[$subclass.$lc_library_name])) {
			include_once $orange_paths[$subclass.$lc_library_name];
			if (class_exists($subclass.$lc_library_name, FALSE)) {
				$prefix = $subclass;
			} else {
				log_message('debug', $path.' exists, but does not declare '.$subclass);
			}
		}

		return $this->_ci_init_library($library_name, $prefix, $params, $object_name);
	}

	protected function _ci_load_library($class, $params = NULL, $object_name = NULL) {
		// Get the class name, and while we're at it trim any slashes.
		// The directory path can be included as part of the class name,
		// but we don't want a leading slash
		$class = str_replace('.php', '', trim($class, '/'));

		// Was the path included with the class name?
		// We look for a slash to determine this
		if (($last_slash = strrpos($class, '/')) !== FALSE) 	{
			// Extract the path
			$subdir = substr($class, 0, ++$last_slash);

			// Get the filename from the path
			$class = substr($class, $last_slash);
		} else {
			$subdir = '';
		}

		$class = ucfirst($class);

		// Is this a stock library? There are a few special conditions if so ...
		if (file_exists(BASEPATH.'libraries/'.$subdir.$class.'.php')) {
			return $this->_ci_load_stock_library($class, $subdir, $params, $object_name);
		}

		// Safety: Was the class already loaded by a previous call?
		if (class_exists($class, FALSE)) {
			$property = $object_name;
			if (empty($property)) {
				$property = strtolower($class);
				isset($this->_ci_varmap[$property]) && $property = $this->_ci_varmap[$property];
			}

			$CI =& get_instance();

			if (isset($CI->$property)) {
				log_message('debug', $class.' class already loaded. Second attempt ignored.');
				return;
			}

			return $this->_ci_init_library($class, '', $params, $object_name);
		}

		// Let's search for the requested library file and load it.
		foreach ($this->_ci_library_paths as $path) {
			// BASEPATH has already been checked for
			if ($path === BASEPATH) {
				continue;
			}

			$filepath = $path.'libraries/'.$subdir.$class.'.php';
			// Does the file exist? No? Bummer...
			if (!file_exists($filepath)) {
				continue;
			}

			$pathinfo = pathinfo($filepath);

			$orange_paths = orange_autoload_files::paths('libraries');

			$lc_library_name = strtolower($pathinfo['filename']);

			if (isset($orange_paths[$lc_library_name])) {
				include $orange_paths[$lc_library_name];

				return $this->_ci_init_library($class, '', $params, $object_name);
			}
		}

		// One last attempt. Maybe the library is in a subdirectory, but it wasn't specified?
		if ($subdir === '') {
			return $this->_ci_load_library($class.'/'.$class, $params, $object_name);
		}

		// If we got this far we were unable to find the requested class.
		log_message('error', 'Unable to load the requested class: '.$class);
		show_error('Unable to load the requested class: '.$class);
	}

	public function model($model, $name = '', $db_conn = FALSE) 	{
		if (empty($model)) {
			return $this;
		} elseif (is_array($model)) {
			foreach ($model as $key => $value) {
				is_int($key) ? $this->model($value, '', $db_conn) : $this->model($key, $value, $db_conn);
			}

			return $this;
		}

		$path = '';

		// Is the model in a sub-folder? If so, parse out the filename and path.
		if (($last_slash = strrpos($model, '/')) !== FALSE) 	{
			// The path is in front of the last slash
			$path = substr($model, 0, ++$last_slash);

			// And the model name behind it
			$model = substr($model, $last_slash);
		}

		if (empty($name)) {
			$name = $model;
		}

		if (in_array($name, $this->_ci_models, TRUE)) 	{
			return $this;
		}

		$CI =& get_instance();

		if (isset($CI->$name)) {
			throw new RuntimeException('The model name you are loading is the name of a resource that is already being used: '.$name);
		}

		if ($db_conn !== FALSE && ! class_exists('CI_DB', FALSE)) 	{
			if ($db_conn === TRUE) {
				$db_conn = '';
			}

			$this->database($db_conn, FALSE, TRUE);
		}

		/* get a array of all the models */
		$orange_paths = orange_autoload_files::paths('models');

		if ($remap = $this->_remap($model)) {
			$name = strtolower($model);
			$model = $remap;
		}

		if (!isset($orange_paths[$model])) {
			throw new RuntimeException('Could not load Model "'.$model.'"');
		}

		require $orange_paths[$model];

		$this->_ci_models[] = $name;

		$CI->$name = new $model();

		log_message('info', 'Model "'.$model.'" initialized');

		return $this;
	}

	public function entity($entity_name) {
		if ($remap = $this->_remap($entity_name)) {
			$entity_name = $remap;
		}

		/* try to load this entity */
		if (!class_exists($entity_name,true)) {
			log_message('error', 'Non-existent class: '.$entity_name);

			throw new Exception('Non-existent class: '.$entity_name);
		}

		/* on single return this entity */
		return new $entity_name();
	}

	protected function _remap($name) {
		/* load config on demand */
		if (!$this->remap) {
			$autoload = load_config('autoload','autoload');

			$this->remap = (isset($autoload['remap'])) ? $autoload['remap'] : [];
		}

		/* normalize the name */
		$lowercase_name = strtolower($name);

		return (isset($this->remap[$lowercase_name])) ? $this->remap[$lowercase_name] : false;
	}

} /* end class */