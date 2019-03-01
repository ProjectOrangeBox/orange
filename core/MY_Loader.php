<?php
/**
 * Orange
 *
 * An open source extensions for CodeIgniter 3.x
 *
 * This content is released under the MIT License (MIT)
 * Copyright (c) 2014 - 2019, Project Orange Box
 */

/**
 * Extension to CodeIgniter Load Class
 *
 * Overrides Model & library
 * Adds Entity,
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2019
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version v2.0
 * @filesource
 *
 */
class MY_Loader extends \CI_Loader
{

	/**
	 *
	 * Constructor
	 *
	 * @access public
	 *
	 */
	public function __construct()
	{
		$this->_ci_classes =& is_loaded();

		log_message('info', 'MY_Loader Class Initialized');
	}

	/**
	 *
	 * Load a library
	 *
	 * @access public
	 *
	 * @param $library
	 * @param $params NULL
	 * @param $object_name NULL
	 *
	 * @return MY_Loader
	 *
	 */
	public function library($library, $params = null, $object_name = null)
	{
		if (is_array($library)) {
			foreach ($library as $l) {
				$this->library($l);
			}

			return $this;
		}

		return $this->_init_object($library, $params, $object_name);
	}

	/**
	 *
	 * Load a model and optionally attach a database connection
	 *
	 * @access public
	 *
	 * @param $model
	 * @param $name
	 * @param $db_conn FALSE
	 *
	 * @return MY_Loader
	 *
	 */
	public function model($model, $name = '', $db_conn = false)
	{
		if (is_array($model)) {
			foreach ($model as $m) {
				$this->model($m, '', $db_conn);
			}

			return $this;
		}

		if ($db_conn !== false && ! class_exists('CI_DB', false)) {
			if ($db_conn === true) {
				$db_conn = '';
			}

			$this->database($db_conn, false, true);
		}

		return $this->_init_object($model, null, $name);
	}

	/**
	 *
	 * Return a new model entity
	 *
	 * @access public
	 *
	 * @param string $name
	 *
	 * @return Object
	 *
	 */
	public function entity(string $name)
	{
		$name = basename(strtolower($name), '.php');

		if (!$object =& $this->instantiate($name, '', false)) {
			throw new RuntimeException('Could not locate class entity '.$name);
		}

		return $object;
	}

	/**
	 *
	 * Create a new instance
	 *
	 * @access protected
	 *
	 * @param $name
	 * @param $params null
	 * @param $object_name null
	 *
	 * @return MY_Loader
	 *
	 */
	protected function _init_object(string $name, array $params = null, string $object_name = null) : MY_Loader
	{
		$name = basename(strtolower($name), '.php');

		$config = config($name, []);

		if (is_array($params)) {
			$config = array_replace($config, $params);
		}

		if (!$this->instantiate($name, '', true, $config, $object_name)) {
			if (!$this->instantiate($name, 'ci_', true, $config, $object_name)) {
				throw new RuntimeException('Could not locate class '.$name);
			}
		}

		return $this;
	}

	/**
	 *
	 * Instantiate the class
	 *
	 * @access protected
	 *
	 * @param string $name super object object name
	 * @param string $prefix prefix to look for if any
	 * @param bool $attach weither to attach it to the CodeIgniter SuperObject
	 * @param array $config []
	 * @param string $object_name null
	 *
	 * @return bool | object if attach is false
	 *
	 */
	protected function instantiate(string $name, string $prefix = '', bool $attach = false, array &$config=[], $object_name=null)
	{
		/**
		 * Warning multiple exits (6)
		 */
		$CI = get_instance();

		/* is it already setup? */
		if (isset($CI->$name)) {
			/* exit 1 */
			return $this;
		}

		$find = $name;
		$autoload = load_config('autoload', 'autoload');

		/* is this a service */
		$services = $autoload['services'];

		if (is_array($services)) {
			if (isset($services[$name])) {
				/* it's a service */
				$namespaced_class = $services[$name];

				if ($attach) {
					$CI->$name = new $namespaced_class($config);

					/* exit 2 */
					return true;
				} else {
					/* create and return */

					/* exit 3 */
					return new $namespaced_class($config);
				}
			}
		}

		/* remap this? */
		if (!$object_name) {
			if (is_array($autoload['remap'])) {
				$remap = array_reverse($autoload['remap'], true);

				if (isset($remap[$find])) {
					$find = $remap[$find];
				}
			}
		} else {
			$find = $object_name;
		}

		/* old school locator */
		if (class_exists($prefix.$find)) {
			$path = orange_locator::class($prefix.$find);

			$this->loaded[$name] = $path;

			$class_name = $prefix.basename(strtolower($path), '.php');

			if ($attach) {
				$CI->$name = new $class_name($config);

				/* exit 4 */
				return true;
			} else {
				/* create and return */

				/* exit 5 */
				return new $class_name($config);
			}
		}

		/* exit 6 */
		return false;
	}
} /* end class */
