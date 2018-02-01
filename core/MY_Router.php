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

class MY_Router extends CI_Router {
	protected $package = '';
	protected $clean_controller = null;
	protected $clean_method = null;

	protected function _set_default_controller() {
		if (empty($this->default_controller)) {
			throw new Exception('Unable to determine what should be displayed. A default route has not been specified in the routing file.');
		}

		$segments = $this->controller_method($this->default_controller);

		if (PHP_SAPI === 'cli' or defined('STDIN')) {
			$segments[1] = substr($segments[1], 0, -6).'CliAction';
		}

		$this->set_class($segments[0]);
		$this->set_method($segments[1]);
		$this->uri->rsegments = [
			1 => $segments[0],
			2 => $segments[1],
		];
	}

	public function _validate_request($segments) {
		$uri = implode('/',str_replace('-','_',$segments));
		$op = orange_paths();

		foreach ($op['controllers'] as $key=>$rec) {
			if (preg_match('#^'.$key.'$#', $uri, $matches)) {
				$segs = explode('/',trim($matches[1],'/'));

				$this->package = $rec['package'];
				$this->directory = $rec['directory'];
				$this->clean_controller = $rec['clean_controller'];
				$this->clean_method = (empty($segs[0])) ? 'index' : strtolower($segs[0]);

				$segments = [];

				$segments[0] = $this->clean_controller.'Controller';
				$segments[1] = $this->clean_method.$this->fetch_request_method(true).'Action';

				/* shift off method */
				array_shift($segs);

				foreach ($segs as $uu) {
					$segments[] = $uu;
				}

				return $segments;
			}
		}

		/* nothing found */
		$this->directory = '';

		log_message('debug', 'MY_Router::_validate_request::404');

		return $this->controller_method($this->routes['404_override']);
	}

	public function fetch_request_method($filter_get=false) {
		$method = isset($_SERVER['REQUEST_METHOD']) ? ucfirst(strtolower($_SERVER['REQUEST_METHOD'])) : 'Cli';

		return ($filter_get && $method == 'Get') ? '' : $method;
	}

	public function fetch_directory() {
		return ($this->package != '') ? substr($this->directory, strlen('../../'.$this->package.'controllers/')) : $this->directory;
	}

	public function fetch_class($clean=false) {
		return ($clean) ? $this->clean_controller : $this->class;
	}

	public function fetch_method($clean=false) {
		return ($clean) ? $this->clean_method : $this->method;
	}

	protected function controller_method($input) {
		$segments[0] = $input;
		$segments[1] = 'index';

		if (strpos($input, '/') !== false) {
			$segments = explode('/', $input, 2);
		}

		$this->clean_controller = ucfirst(strtolower($segments[0]));
		$this->clean_method = strtolower($segments[1]);

		$segments[0] .= 'Controller';
		$segments[1] .= 'Action';

		return $segments;
	}

} /* end file */
