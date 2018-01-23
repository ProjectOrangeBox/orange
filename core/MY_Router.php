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
		log_message('debug', 'MY_Router::_set_default_controller');

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

		log_message('debug', 'No URI present. Default controller set.');
	}

	public function _validate_request($segments) {
		log_message('debug', 'MY_Router::_validate_request');

		$segments = $this->method_b($segments,false);

		if (is_array($segments)) {
			#die(var_dump($this->class,$this->method,$this->package,$this->directory,$this->clean_controller,$this->clean_method,$s));

			return $segments;
		}
		
		/* nothing found */
		
		$this->directory = '';

		log_message('debug', 'MY_Router::_validate_request::404');

		return $this->controller_method($this->routes['404_override']);
	}

	public function method_b($segments) {
		$uri = implode('/',str_replace('-','_',$segments));
		$op = get_orange_paths();

		foreach ($op['caches']['controllers'] as $key=>$controller_file) {
			if (preg_match('#^'.$key.'$#', $uri, $matches)) {
				$m = explode('/',trim($matches[1],'/'));

				$cntr = str_replace(ROOTPATH.'/','',dirname(dirname($controller_file)));
				$where_is_controller = strpos($cntr,'/controllers');
				
				$this->package = ($where_is_controller > 0) ? substr($cntr,0,$where_is_controller).'/' : $cntr.'/';
				$this->directory = str_replace(ROOTPATH,'../..',dirname($controller_file)).'/';
				
				$this->clean_controller = basename($controller_file,'Controller.php');
				$this->clean_method = (empty($m[0])) ? 'index' : strtolower($m[0]);

				$segments = [];

				$segments[0] = $this->clean_controller.'Controller';
				$segments[1] = $this->clean_method.$this->fetch_request_method(true).'Action';

				array_shift($m);

				foreach ($m as $uu) {
					$segments[] = $uu;
				}

				return $segments;
			}
		}

		return false;
	}

	public function method_a($segments) {
		$search_path = explode(PATH_SEPARATOR, get_include_path());

		foreach ($segments as $folder) {
			$segments[0] = str_replace('-', '_', $folder);

			foreach ($search_path as $path) {
				$path = rtrim($path, '/').'/';
				$this->package = str_replace(ROOTPATH.'/', '', $path);
				$segments[1] = ((isset($segments[1])) ? str_replace('-', '_', $segments[1]) : 'index');

				if (file_exists($path.'controllers/'.$this->directory.ucfirst(strtolower($segments[0])).'Controller.php')) {
					if (!file_exists($path.'controllers/'.$this->directory.strtolower($segments[0]).'/'.ucfirst(strtolower($segments[1])).'Controller.php')) {

						$this->clean_controller = ucfirst(strtolower($segments[0]));
						$segments[0] = $this->clean_controller.'Controller';

						$this->clean_method = strtolower($segments[1]);

						$request = $this->fetch_request_method();
						$segments[1] .= (($request == 'Get') ? '' : $request).'Action';

						if ($this->package != 'application') {
							$this->directory = '../../'.$this->package.'controllers/'.$this->directory;
						}

						log_message('debug', 'MY_Router::$this->directory['.$this->directory.'] $segments['.implode(',', $segments).']');

						return $segments;
					}
				}
			}

			$this->set_directory(array_shift($segments), true);
		}

		return false;
	}

	public function fetch_request_method($filter_get=false) {
		log_message('debug', 'MY_Router::fetch_request_method');

		$method = isset($_SERVER['REQUEST_METHOD']) ? ucfirst(strtolower($_SERVER['REQUEST_METHOD'])) : 'Cli';

		return ($filter_get && $method == 'Get') ? '' : $method;
	}

	public function fetch_directory() {
		log_message('debug', 'MY_Router::fetch_directory');

		return ($this->package != '') ? substr($this->directory, strlen('../../'.$this->package.'controllers/')) : $this->directory;
	}

	public function fetch_class($clean=false) {
		log_message('debug', 'MY_Router::fetch_class');

		return ($clean) ? $this->clean_controller : $this->class;
	}

	public function fetch_method($clean=false) {
		log_message('debug', 'MY_Router::fetch_method');

		return ($clean) ? $this->clean_method : $this->method;
	}

	protected function controller_method($input) {
		log_message('debug', 'MY_Router::controller_method');

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
