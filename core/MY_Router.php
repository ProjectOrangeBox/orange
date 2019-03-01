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
 * Extension to CodeIgniter Router Class
 *
 * Handles login, logout, refresh user data
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2019
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version v2.0
 * @filesource
 *
 * @uses # o_user_model - Orange User Model
 * @uses # session - CodeIgniter Session
 * @uses # event - Orange event
 * @uses # errors - Orange errors
 * @uses # controller - CodeIgniter Controller
 * @uses # output - CodeIgniter Output
 *
 * @config username min length
 * @config username max length
 *
 * @define NOBODY_USER_ID
 * @define ADMIN_ROLE_ID
 *
 */

class MY_Router extends \CI_Router
{
	/**
	 * Storage for the clean version of the Controller Class without the Controller Suffix
	 *
	 * @var string
	 */
	protected $clean_class = null;

	/**
	 * Storage for the Clean version of the method without the HTTP method and Prefix
	 *
	 * @var string
	 */
	protected $clean_method = null;

	/**
	 * Storage for loaded routes
	 *
	 * @var Array
	 */
	protected $loaded_routes = [];

	/**
	 * Storage for current route
	 *
	 * @var string
	 */
	protected $current_route;

	/**
	 * Storage for request handlers
	 *
	 * @var Array
	 */
	protected $requests = [];

	/**
	 * Storage for responds handlers
	 *
	 * @var Array
	 */
	protected $responds = [];

	/**
	 *
	 * Register Request handlers
	 *
	 * @access public
	 *
	 * @param mixed
	 *
	 * @return void
	 *
	 * #### Example
	 * ```php
	 * 'stock/(.*)' => function($url,$router) {
	 *  $router->on_request('PublicMiddleware','GuiMiddleware','NavbarMiddleware');
	 *
	 *	return 'vendor_stock/vendor/'.$url;
	 * },
	 * ```
	 */
	public function on_request() : void
	{
		$this->requests = func_get_args();
	}

	/**
	 *
	 * Register Responds handlers
	 *
	 * @access public
	 *
	 * @param mixed
	 *
	 * @return void
	 *
	 * #### Example
	 * ```php
	 * 'stock/(.*)' => function($url,$router) {
	 *  $router->on_responds('GuiMiddleware');
	 *
	 *	return 'vendor_stock/vendor/'.$url;
	 * },
	 * ```
	 */
	public function on_responds() : void
	{
		$this->responds = func_get_args();
	}

	/**
	 *
	 * Handle the "Middleware" Filter for input
	 *
	 * @access public
	 *
	 * @param &$ci
	 *
	 * @return void
	 *
	 */
	public function handle_requests(&$ci) : void
	{
		foreach ($this->requests as $middleware) {
			(new $middleware($ci))->request();
		}
	}

	/**
	 *
	 * Handle the "Middleware" Filter for output
	 *
	 * @access public
	 *
	 * @param &$ci
	 * @param string $output
	 *
	 * @return string
	 *
	 */
	public function handle_responds(&$ci, string $output) : string
	{
		foreach ($this->responds as $middleware) {
			$output = (new $middleware($ci))->responds($output);
		}

		return $output;
	}

	/**
	 *
	 * Search the controllers for the matching path
	 * Type Hinting turned off because this parent CodeIgniter Method do not support it.
	 *
	 * @access public
	 *
	 * @param array $segments
	 *
	 * @return
	 *
	 */
	public function _validate_request($segments)
	{
		$uri = implode('/', str_replace('-', '_', $segments));

		foreach (orange_locator::controllers() as $key=>$rec) {
			if (preg_match('#^'.$key.'$#', strtolower($uri), $matches)) {
				$segs = explode('/', trim($matches[1], '/'));

				$this->directory = $rec['directory'];
				$this->clean_class = $rec['clean_controller'];

				/**
				 * if the method is set on the controller array use that instead
				 * this captures 404s
				 */
				if ($rec['method']) {
					$this->clean_method = $rec['method'];
				} else {
					$this->clean_method = (empty($segs[0])) ? 'index' : strtolower($segs[0]);
				}

				$segments = [];

				$segments[0] = $this->clean_class.'Controller';
				$segments[1] = $this->clean_method.$this->fetch_request_method(true).'Action';

				array_shift($segs);

				foreach ($segs as $uu) {
					$segments[] = $uu;
				}

				return $segments;
			}
		}
	}

	/**
	 *
	 * Get the current request method
	 *
	 * @access public
	 *
	 * @param $filter_get determine if you want GET (the HTTP default) filtered out [false]
	 *
	 * @return string
	 *
	 */
	public function fetch_request_method($filter_get=false) : string
	{
		$method = isset($_SERVER['REQUEST_METHOD']) ? ucfirst(strtolower($_SERVER['REQUEST_METHOD'])) : 'Cli';

		return ($filter_get && $method == 'Get') ? '' : $method;
	}

	/**
	 *
	 * Get the current directory
	 *
	 * @access public
	 *
	 * @return string
	 *
	 */
	public function fetch_directory() : string
	{
		return substr($this->directory, strpos($this->directory, '/controllers/') + 13);
	}

	/**
	 *
	 * Get the current Class (Controller)
	 *
	 * @access public
	 *
	 * @param bool $clean determine if you want the Controller prefix stripped [false]
	 *
	 * @return string
	 *
	 */
	public function fetch_class(bool $clean=false) : string
	{
		return ($clean) ? $this->clean_class : $this->class;
	}

	/**
	 *
	 * Get the current Method (Action)
	 *
	 * @access public
	 *
	 * @param bool $clean determine if you want the method and action prefix stripped [false]
	 *
	 * @return string
	 *
	 */
	public function fetch_method(bool $clean=false) : string
	{
		return ($clean) ? $this->clean_method : $this->method;
	}

	/**
	 *
	 * Get the current route
	 *
	 * @access public
	 *
	 * @return string
	 *
	 */
	public function fetch_route() : string
	{
		if (!$this->current_route) {
			$this->current_route = strtolower(trim($this->fetch_directory().$this->fetch_class(true).'/'.$this->fetch_method(true), '/'));
		}

		return $this->current_route;
	}

	/**
	 *
	 * Determine if a route is available
	 *
	 * @access public
	 *
	 * @param string $directory
	 * @param string $class
	 * @param string $method
	 *
	 * @return bool
	 *
	 */
	public function route(string &$directory, string &$class, string &$method) : bool
	{
		$class = ucfirst($class);

		$e404 = false;

		if (empty($class) || !file_exists(APPPATH.'controllers/'.$directory.$class.'.php')) {
			$e404 = true;
		} else {
			/* this brings in the controller file */
			require_once(APPPATH.'controllers/'.$directory.$class.'.php');

			if (!class_exists($class, false) || $method[0] === '_' || method_exists('CI_Controller', $method)) {
				$e404 = true;
			} elseif (method_exists($class, '_remap')) {
				$params = array($method, array_slice($URI->rsegments, 2));
				$method = '_remap';
			} elseif (!method_exists($class, $method)) {
				$e404 = true;
			} elseif (!is_callable(array($class, $method))) {
				$reflection = new ReflectionMethod($class, $method);

				if (!$reflection->isPublic() || $reflection->isConstructor()) {
					$e404 = true;
				}
			}
		}

		return $e404;
	}

	/**
	 *
	 * Set the default controller
	 *
	 * @access protected
	 *
	 * @throws \Exception
	 * @return void
	 *
	 */
	protected function _set_default_controller() : void
	{
		if (empty($this->default_controller)) {
			throw new \Exception('Unable to determine what should be displayed. A default route has not been specified in the routing file.');
		}

		$segments = $this->controller_method($this->default_controller);

		if (PHP_SAPI === 'cli' or defined('STDIN')) {
			$segments[1] = substr($segments[1], 0, -6).'CliAction';
		}

		$this->set_class($segments[0]);
		$this->set_method($segments[1]);
		$this->uri->rsegments = [1=>$segments[0],2=>$segments[1]];
		$this->directory = '';
	}

	/**
	 *
	 * Set the Controller and Method
	 *
	 * @access protected
	 *
	 * @param string $input
	 *
	 * @return array
	 *
	 */
	protected function controller_method(string $input) : array
	{
		$segments[0] = $input;
		$segments[1] = 'index';

		if (strpos($input, '/') !== false) {
			$segments = explode('/', $input, 2);
		}

		$this->clean_class = ucfirst(strtolower($segments[0]));
		$this->clean_method = strtolower($segments[1]);

		$segments[0] .= 'Controller';
		$segments[1] .= 'Action';

		return $segments;
	}

	/**
	 *
	 * Set the default route
	 *
	 * @access protected
	 *
	 * @return void
	 *
	 */
	protected function _set_routing() : void
	{
		$route = load_config('routes', 'route');

		/**
		 *
		 * Validate & get reserved routes
		 *
		 */
		if (isset($route) && is_array($route)) {
			isset($route['default_controller']) && $this->default_controller = $route['default_controller'];

			unset($route['default_controller']);

			$this->loaded_routes = $route;
		}

		/**
		 *
		 * Is there anything to parse?
		 *
		 */
		if ($this->uri->uri_string !== '') {
			$this->_parse_routes();
		} else {
			$this->_set_default_controller();

			$this->_parse_routes(true);
		}
	}

	/**
	 *
	 * Handle route
	 *
	 * @access protected
	 *
	 * @param bool $skip_set false
	 *
	 * @return void
	 *
	 */
	protected function _parse_routes(bool $skip_set = false) : void
	{
		// Turn the segment array into a URI string
		$uri = implode('/', $this->uri->segments);

		// Get HTTP verb
		$http_verb = $this->fetch_request_method(false);

		// Loop through the route array looking for wildcards
		foreach ($this->loaded_routes as $key=>$val) {
			// Check if route format is using HTTP verbs
			if (is_array($val)) {
				$val = array_change_key_case($val, CASE_LOWER);

				if (isset($val[$http_verb])) {
					$val = $val[$http_verb];
				} else {
					continue;
				}
			}

			// Convert wildcards to RegEx
			$key = str_replace(array(':any',':num'), array('[^/]+','[0-9]+'), $key);

			// Does the RegEx match?
			if (preg_match('#^'.$key.'$#', $uri, $matches)) {
				// Are we using callbacks to process back-references?
				if (!is_string($val) && is_callable($val)) {
					// Remove the original string from the matches array.
					array_shift($matches);

					$matches[] = &$this;

					// Execute the callback using the values in matches as its parameters.
					$val = call_user_func_array($val, $matches);
				} elseif (strpos($val, '$') !== false && strpos($key, '(') !== false) {
					// Are we using the default routing method for back-references?
					$val = preg_replace('#^'.$key.'$#', $val, $uri);
				}

				if (!$skip_set) {
					$this->_set_request(explode('/', $val));
				}

				return;
			}
		}

		/**
		 *
		 * If we got this far it means we didn't encounter a
		 * matching route so we'll set the site default route
		 *
		 */
		$this->_set_request(array_values($this->uri->segments));
	}
} /* end class */
