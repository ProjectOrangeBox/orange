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
 * core: config
 * libraries:
 * models:
 * helpers:
 *
 */

class MY_Router extends CI_Router {
	protected $package = ''; /* what package are we currently looking for a controller in? */
	protected $clean_controller = null; /* storage for the controller name without Controller on the end */
	protected $clean_method = null; /* storage for the method without the request Method + Action on the end */
	
	/**
	 * Set default controller
	 *
	 * @author Don Myers
	 * @return void
	 *
	 * overridden because we need to add Controller and Action on controller name and method
	 *
	 */
	protected function _set_default_controller() {
		if (empty($this->default_controller)) {
			throw new Exception('Unable to determine what should be displayed. A default route has not been specified in the routing file.');
		}

		$segments = $this->controller_method($this->default_controller);

		if (PHP_SAPI === 'cli' OR defined('STDIN')) {
			/* strip Action and add CliAction */
			$segments[1] = substr($segments[1], 0, -6) . 'CliAction';
		}

		$this->set_class($segments[0]);
		$this->set_method($segments[1]);

		// Assign routed segments,index starting from 1
		$this->uri->rsegments = [
			1 => $segments[0],
			2 => $segments[1],
		];

		log_message('debug', 'No URI present. Default controller set.');
	}

	/**
	 * Validate request
	 *
	 * Attempts validate the URI request and determine the controller path.
	 *
	 * @author Don Myers
	 * @param	 array $segments URI segments
	 * @return array URI segments
	 *
	 *	overridden because we handle multiple folder levels
	 *
	 */
	public function _validate_request($segments) {
		/*
		we just need to see if it's there not load it
		we also ALWAYS convert - to _
		 */
		$search_path = explode(PATH_SEPARATOR, get_include_path());

		/* let's find that controller */
		foreach ($segments as $folder) {
			/* always convert - to _ */
			$segments[0] = str_replace('-', '_', $folder);

			foreach ($search_path as $path) {
				$path = rtrim($path, '/') . '/';

				$this->package = str_replace(ROOTPATH . '/', '', $path);

				$segments[1] = ((isset($segments[1])) ? str_replace('-', '_', $segments[1]) : 'index');

				if (file_exists($path . 'controllers/' . $this->directory . ucfirst(strtolower($segments[0])) . 'Controller.php')) {
					if (!file_exists($path . 'controllers/' . $this->directory . strtolower($segments[0]) . '/' . ucfirst(strtolower($segments[1])) . 'Controller.php')) {
						/* yes! then segment 0 is the controller */
						$this->clean_controller = ucfirst(strtolower($segments[0]));

						$segments[0] = $this->clean_controller . 'Controller';

						/* make sure we have a method and add Action (along with the REST stuff) */
						$this->clean_method = strtolower($segments[1]);

						/* http request method - this make the CI 3 method invalid */
						$request = $this->fetch_request_method();

						$segments[1] .= (($request == 'Get') ? '' : $request) . 'Action';

						/* re-route codeigniter.php controller loading */
						if ($this->package != 'application') {
							$this->directory = '../../' . $this->package . 'controllers/' . $this->directory;
						}

						/* return the controller,method and anything else */
						log_message('debug', 'MY_Router::_validate_request::$segments::' . $this->directory . '::' . implode('::', $segments));

						return $segments;
					}
				}
			}

			/* nope! shift off the beginning as a folder level */
			$this->set_directory(array_shift($segments), true);
		}

		/* ERROR controller in application folder */
		$this->directory = '';

		log_message('debug', 'MY_Router::_validate_request::404');

		return $this->controller_method($this->routes['404_override']);
	}

	public function fetch_request_method() {
		/* input also has this method but we always return strtolower + ucfirst */
		return isset($_SERVER['REQUEST_METHOD']) ? ucfirst(strtolower($_SERVER['REQUEST_METHOD'])) : 'Cli';
	}

	/**
	 * fetch_directory function.
	 * 
	 * @author Don Myers
	 * @access public
	 * @return void
	 */
	public function fetch_directory() {
		/* strip out controller path re-routing */
		return ($this->package != '') ? substr($this->directory, strlen('../../' . $this->package . 'controllers/')) : $this->directory;
	}

	/**
	 * Fetch the current class
	 *
	 * @deprecated	3.0.0	Read the 'class' property instead
	 * @return	string
	 */
	public function fetch_class($clean=false) {
		return ($clean) ? $this->clean_controller : $this->class;
	}

	/**
	 * Fetch the current method
	 *
	 * @deprecated	3.0.0	Read the 'method' property instead
	 * @return	string
	 */
	public function fetch_method($clean=false) {
		return ($clean) ? $this->clean_method : $this->method;
	}

	/**
	 * controller_method function.
	 * 
	 * @author Don Myers
	 * @access protected
	 * @param mixed $input
	 * @return void
	 */
	protected function controller_method($input) {
		$segments[0] = $input;
		$segments[1] = 'index';

		/* These can only be top level controllers so does this include / to indicate a method? */
		if (strpos($input, '/') !== false) {
			$segments = explode('/', $input, 2);
		}

		$this->clean_controller = ucfirst(strtolower($segments[0]));
		$this->clean_method = strtolower($segments[1]);

		$segments[0] .= 'Controller';
		$segments[1] .= 'Action';

		return $segments;
	}

} /* end my router */