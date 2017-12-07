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
 * core: load
 * libraries: event
 * models:
 * helpers:
 * functions: setting
 *
 */

class MY_Controller extends CI_Controller {
	/* CodeIgniter output cache this page for (in seconds)*/
	public $cache_page_for = null;

	/* your basic admin scaffolding */
	public $controller        = ''; /* controller name */
	public $controller_path   = null; /* url to this controller */
	public $controller_title  = ''; /* used in various places human singular */
	public $controller_titles = ''; /* used in various places human plural */

	/* the children controllers can set these to have additional objects auto loaded */
	public $libraries = null;
	public $helpers   = null;
	public $models    = null;

	/* database name/value pairs */
	public $catalogs = null;

	/* storage for the view data (variables) */
	public $data = [];

	/* place to store the controllers "default" model so you can change it simply by changing this property ie. $this->{$this->controller_model}-> */
	public $controller_model;

	/* this contains the middleware that ran */
	public $controller_middleware_ran = [];

	/* the middleware body class names */
	public $controller_middleware_as_body_classes = [];

	/* setup our base controller */
	public function __construct() {
		parent::__construct();

		/* start middleware */
		require ORANGEPATH . '/libraries/Middleware_base.php';

		require APPPATH . '/config/middleware.php';

		$base_middleware = $middleware;
		
		if (file_exists(APPPATH . '/config/'.ENVIRONMENT.'/middleware.php')) {
			include APPPATH . '/config/'.ENVIRONMENT.'/middleware.php';
		
			$base_middleware = array_merge($base_middleware,$middleware);
		}

		foreach ($base_middleware as $re => $middleware_files) {
			if (preg_match('@' . str_replace('*', '(.*)', $re) . '@', '/' . ci()->uri->uri_string, $matches, PREG_OFFSET_CAPTURE, 0) == 1) {
				break; /* break out of foreach loop */
			}
		}

		foreach ((array) $middleware_files as $middleware_file) {
			if (class_exists($middleware_file)) {
				$this->controller_middleware_as_body_classes[] = substr($middleware_file, 0, -10);
				$this->controller_middleware_ran[]             = $middleware_file;


				(new $middleware_file($this))->run();
			} else {
				throw new Exception('middleware "'.$middleware_file.'" not found.');
			}
		}
		/* end middleware */

		/* is the site even open? */

		/* it's always open on the command line */
		if (php_sapi_name() !== 'cli') {
			/* test the setting */
			if (config('application.site open') !== true) {
				/* but do they have the correct ISOPEN cookie? */
				if ($_COOKIE['ISOPEN'] !== config('application.is open cookie', md5(uniqid(true)))) {
					/* nope! */
					errors::display(503, ['heading' => 'Please Stand By', 'message' => 'Site Down for Maintenance']);
				}
			}
		}

		/* load the other controller libraries, model, helpers */
		if ($this->libraries) {
			$this->load->library((array) $this->libraries);
		}

		if ($this->models) {
			$this->load->model((array) $this->models);
		}

		if ($this->helpers) {
			$this->load->helpers((array) $this->helpers);
		}

		if ($this->catalogs) {
			/*
				$catalog = [
					'bar_catalog'=>'bar_model',
					'foo_catalog'=>['model'=>'foo_catalog','array_key'=>'id{defaults to primary id}','select'=>'id,color{defaults to *}','where'=>['soft_delete'=>0]{defaults to none},'order_by'=>'color [asc|desc]'{defaults to none}],
				]
			*/
			foreach ($this->catalogs as $variable_name => $args) {
			
				if (!is_array($args)) {
					$model_name = $args;
					$args = [];
				} else {
					$model_name = $args['model'];
				}

				/* load the model */
				$this->load->model($model_name);

				/* does the catalog method exist on the loaded model? */
				if (method_exists($this->$model_name, 'catalog')) {
					/* catalog($array_key = null, $select = null, $where = null, $order_by = null) */
					$this->load->vars($variable_name, $this->$model_name->catalog($args['array_key'], $args['select'], $args['where'], $args['order_by']));
				} else {
					throw new Exception('Method "catalog" doesn\'t exist on "' . $model_name . '"');
				}
			}
		}

		/* load a default model if one is specified */
		if ($this->controller_model) {
			$this->load->model($this->controller_model);
		}

		/* while you could have done this in your onload file - this keeps it "clean" */
		Event::trigger('ci.controller.startup', $this);
		
	} /* end __construct */

} /* end controller */