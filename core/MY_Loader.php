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

	public function __construct() {
		$this->_ci_classes =& is_loaded();

		log_message('info', 'MY_Loader Class Initialized');
	}

	public function library($library, $params = NULL, $object_name = NULL) {
		if (is_array($library)) {
			foreach ($library as $l) {
				$this->library($l);
			}

			return $this;
		}

		return $this->_init_object($library,$params,$object_name);
	}

	public function model($model, $name = '', $db_conn = FALSE) {
		if (is_array($model)) {
			foreach ($model as $m) {
				$this->model($m,'',$db_conn);
			}

			return $this;
		}

		if ($db_conn !== FALSE && ! class_exists('CI_DB', FALSE)) {
			if ($db_conn === TRUE) {
				$db_conn = '';
			}

			$this->database($db_conn, FALSE, TRUE);
		}

		return $this->_init_object($model,null,$name);
	}

	/* this returns the new entity */
	public function entity($name) {
		$name = basename(strtolower($name),'.php');

		if (!$object =& $this->instantiate($name,'',false)) {
			throw new RuntimeException('Could not locate class entity '.$name);
		}

		return $object;
	}

	/**
	*
	* Protected
	*
	*/

	/* used by library and model */
	protected function _init_object($name,$params = null,$object_name = null) {
		$name = basename(strtolower($name),'.php');

		$config = config($name,[]);

		if (is_array($params)) {
			$config = array_replace($config,$params);
		}

		if (!$this->instantiate($name,'',true,$config,$object_name)) {
			if (!$this->instantiate($name,'ci_',true,$config,$object_name)) {
				throw new RuntimeException('Could not locate class '.$name);
			}
		}

		return $this;
	}

	/* used by library, model and entity */
	protected function instantiate($name,$prefix='',$attach=false,&$config=[],$object_name=null) {
		$CI = get_instance();

		/* is it already setup? */
		if (isset($CI->$name)) {
			return $this;
		}

		$find = $name;
		$autoload = load_config('autoload','autoload');

		$success = false;

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

		if (class_exists($prefix.$find)) {
			$path = orange_locator::class($prefix.$find);

			$this->loaded[$name] = $path;

			$class_name = $prefix.basename(strtolower($path),'.php');

			if ($attach) {
				$CI->$name = new $class_name($config);

				$success = true;
			} else {
				/* create and return */
				return new $class_name($config);
			}
		}

		return $success;
	}

} /* end class */
