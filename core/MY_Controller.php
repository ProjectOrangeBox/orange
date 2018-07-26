<?php
/**
 * MY_Controller
 * base controller
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
class MY_Controller extends CI_Controller {
	/**
	 * cache the controllers output in seconds
	 *
	 * @var int
	 */
	public $cache_page_for = null;

	/**
	 * name of the controller
	 *
	 * @var string
	 */
	public $controller = null;

	/**
	 * URL to this controller
	 *
	 * @var string
	 */
	public $controller_path = null;

	/**
	 * libraries to load
	 * available to all methods
	 *
	 * @var array
	 */
	public $libraries = null;

	/**
	 * helpers to load
	 * available to all methods
	 *
	 * @var array
	 */
	public $helpers = null;

	/**
	 * models to load
	 * available to all methods
	 *
	 * @var array
	 */
	public $models = null;

	/**
	 * catalogs to load
	 * available to all methods
	 *
	 * @var array
	 */
	public $catalogs = null;

	/**
	 * universally accessible view data
	 *
	 * @var array
	 */
	public $data = [];

	/**
	 * name of the default controller model
	 *
	 * @var string
	 */
	public $controller_model = null;

	public function __construct() {
		/* let the parent controller do it's work */
		parent::__construct();

		log_message('debug', 'MY_Controller::__construct');

		/* is the site up? */
		if (php_sapi_name() !== 'cli') {
			if (!config('application.site open',true)) {
				if ($_COOKIE['ISOPEN'] !== config('application.is open cookie', md5(uniqid(true)))) {
					ci('errors')->display(503, ['heading' => 'Please Stand By', 'message' => 'Site Down for Maintenance']);
				}
			}
		}

		/* fire off middleware if necessary */
		foreach (orange_middleware::get() as $middleware_file) {
			/* this loads the class / middleware */
			if (class_exists($middleware_file)) {
				/* this fires it off */
				new $middleware_file;
			} else {
				/* class_exists couldn't find it */
				throw new Exception('middleware "'.$middleware_file.'" not found.');
			}
		}

		/* load the libraries, models, helpers, catalogs from the properties as needed */
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
			foreach ($this->catalogs as $variable_name=>$args) {
				if (!is_array($args)) {
					$model_name = $args;
					$args = [];
				} else {
					$model_name = $args['model'];
				}

				$this->load->model($model_name);

				$model_method = (isset($args['method'])) ? $args['method'] : 'catalog';

				if (method_exists($this->$model_name,$model_method)) {
					if ($model_method == 'catalog') {
						$this->load->vars($variable_name, $this->$model_name->$model_method(@$args['array_key'],@$args['select'],@$args['where'],@$args['order_by'],@$args['cache'],@$args['with_deleted']));
					} else {
						$this->load->vars($variable_name, $this->$model_name->$model_method($args));
					}
				} else {
					throw new Exception('Method "'.$model_method.'" doesn\'t exist on "'.$model_name.'"');
				}
			}
		}

		/* does the controller have a "default" model? */
		if ($this->controller_model) {
			$this->load->model(strtolower($this->controller_model));
		}

		/* trigger our start up event */
		ci('event')->trigger('ci.controller.startup', $this);
	}
	
	/* place holder */
	public function indexCliAction(){}
	
} /* end class */
