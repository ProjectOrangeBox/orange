<?php
/**
 * Orange
 *
 * An open source extensions for CodeIgniter 3.x
 *
 * This content is released under the MIT License (MIT)
 * Copyright (c) 2014 - 2019, Project Orange Box
 */

namespace projectorangebox\orange\libraries;

/**
 * HTML Page Building
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2019
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version v2.0
 * @filesource
 *
 * @uses # load - CodeIgniter Loader
 * @uses # output - CodeIgniter Output
 * @uses # event - Orange Event
 * @uses # Orange view(...)
 *
 * @config script_attributes `['src' => '', 'type' => 'text/javascript', 'charset' => 'utf-8']`
 * @config link_attributes `['href' => '', 'type' => 'text/css', 'rel' => 'stylesheet']`
 * @config domready_javascript `document.addEventListener("DOMContentLoaded",function(e){%%});`
 * @config page_prefix `page_`
 * @config page_ array of additional
 * @config page_min boolean
 *
 * @define PAGE_MIN
 *
 */
class Page
{
	const PRIORITY_LOWEST = 10;
	const PRIORITY_LOW = 20;
	const PRIORITY_NORMAL = 50;
	const PRIORITY_HIGH = 80;
	const PRIORITY_HIGHEST = 90;

	/**
	 * storage for the page variables
	 *
	 * @var array
	 */
	protected $variables = [];

	/**
	 * the view path & name of the default template
	 *
	 * @var string
	 */
	protected $default_view = '';

	/**
	 * local storage of page's configuration
	 *
	 * @var array
	 */
	protected $config;

	/**
	 * CodeIgniter Loader Object
	 *
	 * @var object
	 */
	protected $load;

	/**
	 * CodeIgniter Output Object
	 *
	 * @var object
	 */
	protected $output;

	/**
	 * Orange Event Object
	 *
	 * @var object
	 */
	protected $event;

	/**
	 * View variable prefix for all page variables
	 *
	 * @var string
	 */
	protected $page_variable_prefix = '';

	/**
	 * Storage for if the current view is extending another view?
	 *
	 * @var boolean | string
	 */
	protected $extending = false;

	/**
	 *
	 * Constructor
	 *
	 * @access public
	 *
	 * @param array $config []
	 *
	 */
	public function __construct(array &$config=[])
	{
		$this->config = &$config;

		$this->load = &ci('load');
		$this->output = &ci('output');
		$this->event = &ci('event');

		$page_min = $this->config['page_min'];

		if (is_bool($page_min)) {
			$page_min = ($page_min == true) ? '.min' : '';
		}

		/* if it's true then use the default else use what's in page_min config */
		define('PAGE_MIN', $page_min);

		$this->page_variable_prefix = $this->config['page_prefix'] ?? 'page_';

		$page_configs = $this->config[$this->page_variable_prefix];

		if (is_array($page_configs)) {
			foreach ($page_configs as $method=>$parameters) {
				if (method_exists($this, $method)) {
					if (is_array($parameters)) {
						foreach ($parameters as $p) {
							call_user_func([$this,$method], $p);
						}
					} else {
						call_user_func([$this,$method], $parameters);
					}
				}
			}
		}

		log_message('info', 'Page Class Initialized');
	}

	/**
	 *
	 * Set the default view if a view is not provided in render
	 *
	 * @access public
	 *
	 * @param string $template
	 *
	 * @return Page
	 *
	 */
	public function set_default_view(string $view = '') : Page
	{
		$this->default_view = $view;

		return $this;
	}

	/**
	 *
	 * Render view content string
	 * optionally "extending" another "parent" template
	 *
	 * @access public
	 *
	 * @param string $view null
	 * @param array $data null
	 *
	 * @throws \Exception
	 * @return Page
	 *
	 */
	public function render(string $view = null, array $data = null) : Page
	{
		log_message('debug', 'page::render::'.$view);

		$view = ($view) ?? $this->default_view;

		if ($view == null) {
			throw new \Exception('No View provided for page render.');
		}

		/* called everytime - use with caution */
		$this->event->trigger('page.render', $this, $view);

		/* called only when a trigger matches the view */
		$this->event->trigger('page.render.'.$view, $this, $view);

		/* this is going to be the "main" section */
		$view_content = $this->view($view, $data);

		if ($this->extending) {
			$view_content = $this->view($this->extending);
		}

		/* called everytime - use with caution  */
		$this->event->trigger('page.render.content', $view_content, $view, $data);

		/* append to the output responds */
		$this->output->append_output($view_content);

		return $this;
	}

	/**
	 * view
	 * basic view rendering using oranges most basic view function
	 *
	 * @param $view_file string
	 * @param $data array
	 * @param $return mixed
	 *
	 * @return mixed
	 *
	 */
	/**
	 *
	 * Pages View Rendering
	 * using oranges most basic view function
	 *
	 * @access public
	 *
	 * @param string $view_file null
	 * @param array $data null
	 * @param $return true optionally a string which then inserts this into the view data array with this as it's variable name
	 *
	 * @throws \Exception
	 * @return mixed
	 *
	 * #### Example
	 * ```php
	 * ci('page')->view('folder/my_view',['name'=>'Johnny']);
	 * ci('page')->view('folder/my_block',['name'=>'Johnny Appleseed'],'name_block');
	 * ```
	 */
	public function view(string $view_file = null, array $data = null, $return = true)
	{
		$data = (is_array($data)) ? array_merge($this->load->get_vars(), $data) : $this->load->get_vars();

		/**
		 * call core orange function view()
		 *
		 * Throws Exception if view not found.
		 */
		$buffer = view($view_file, $data);

		if (is_string($return)) {
			$this->data($return, $buffer);
		}

		return ($return === true) ? $buffer : $this;
	}

	/**
	 *
	 * Insert view data
	 * wrapper for CodeIgniters Loader
	 *
	 * @access public
	 *
	 * @param $name string or array
	 * @param $value null
	 *
	 * @return Page
	 *
	 * #### Example
	 * ```php
	 * ci('page')->data('name','Johnny');
	 * ci('page')->data(['name'=>'Johnny','age'=>23]);
	 * ```
	 */
	public function data($name, $value = null) : Page
	{
		$this->load->vars($name, $value);

		return $this;
	}

	/**
	 *
	 * Specify a additional template which will be loaded after the view template
	 * this allows you to use a "base" template and extend it with your view
	 * you may only extend 1 template.
	 *
	 * @access public
	 *
	 * @param string $template null
	 *
	 * @throws \Exception
	 * @return Page
	 *
	 * #### Example
	 * ```php
	 * ci('page')->extend('_templates/default');
	 * ```
	 */
	public function extend(string $template = null) : Page
	{
		if ($this->extending) {
			throw new \Exception('You are already extending "'.$this->extending.'" therefore we cannot extend "'.$name.'".');
		}

		$this->extending = $template;

		return $this;
	}

	/**
	 *
	 * Create the html for a link tag
	 * <link href="//maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet">
	 *
	 * @access public
	 *
	 * @param string $file
	 *
	 * @return string
	 *
	 * #### Example
	 * ```php
	 * $html = ci('page')->link_html('/assets/css/style.css');
	 * ```
	 */
	public function link_html(string $file) : string
	{
		return $this->ary2element('link', array_merge($this->config['link_attributes'], ['href' => $file]));
	}

	/**
	 *
	 * Create the html for a script tag
	 * <script src="//cdnjs.cloudflare.com/ajax/libs/handlebars.js/4.0.11/handlebars.min.js"></script>
	 *
	 * @access public
	 *
	 * @param string $file
	 *
	 * @throws
	 * @return string
	 *
	 * #### Example
	 * ```php
	 *
	 * ```
	 */
	public function script_html(string $file) : string
	{
		return $this->ary2element('script', array_merge($this->config['script_attributes'], ['src' => $file]));
	}

	/**
	 *
	 * Convert a key value pair array into html attributes
	 *
	 * @access public
	 *
	 * @param string $element
	 * @param array $attributes
	 * @param $content false
	 *
	 * @throws
	 * @return string
	 *
	 * #### Example
	 * ```php
	 * $html = ci('page')->ary2element('a',['class'=>'bold','id'=>'id3'],'link!');
	 * ```
	 */
	public function ary2element(string $element, array $attributes, string $content = '') : string
	{
		/* uses CodeIgniter Common.php _stringify_attributes function */

		return (in_array($element, ['area','base','br','col','embed','hr','img','input','link','meta','param','source','track','wbr'])) ?
			'<'.$element._stringify_attributes($attributes).'/>' :
			'<'.$element._stringify_attributes($attributes).'>'.$content.'</'.$element.'>';
	}

	/**
	 *
	 * Add a meta tag to the view variable
	 * <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
	 *
	 * @access public
	 *
	 * @param $attr
	 * @param string $name null
	 * @param string $content null
	 * @param int $priority PAGE::PRIORITY_NORMAL
	 *
	 * @throws
	 * @return Page
	 *
	 * #### Example
	 * ```php
	 * ci('page')->meta('charset','UTF-8');
	 * ```
	 */
	public function meta($attr, string $name = null, string $content = null, int $priority = PAGE::PRIORITY_NORMAL) : Page
	{
		if (is_array($attr)) {
			extract($attr);
		}

		return $this->add('meta', '<meta '.$attr.'="'.$name.'"'.(($content) ? ' content="'.$content.'"' : '').'>'.PHP_EOL, $priority);
	}

	/**
	 *
	 * Add javascript to the page script view variable
	 *
	 * @access public
	 *
	 * @param string $script
	 * @param int $priority PAGE::PRIORITY_NORMAL
	 *
	 * @return Page
	 *
	 * #### Example
	 * ```php
	 * ci('page')->script('alert("Page Ready!");');
	 * ```
	 */
	public function script(string $script, int $priority = PAGE::PRIORITY_NORMAL) : Page
	{
		return $this->add('script', $script.PHP_EOL, $priority);
	}

	/**
	 *
	 * Add domready javascript to the page domready view variable
	 *
	 * @access public
	 *
	 * @param string $script
	 * @param int $priority PAGE::PRIORITY_NORMAL
	 *
	 * @return Page
	 *
	 * #### Example
	 * ```php
	 * ci('page')->domready('alert("Page Ready!");');
	 * ```
	 */
	public function domready(string $script, int $priority = PAGE::PRIORITY_NORMAL) : Page
	{
		return $this->add('domready', $script.PHP_EOL, $priority);
	}

	/**
	 *
	 * Add Title to the page title view variable
	 *
	 * @access public
	 *
	 * @param string $title
	 * @param int $priority PAGE::PRIORITY_NORMAL
	 *
	 * @return Page
	 *
	 * #### Example
	 * ```php
	 * ci('page')->title('My Web Page');
	 * ```
	 */
	public function title(string $title = '', int $priority = PAGE::PRIORITY_NORMAL) : Page
	{
		return $this->add('title', $title, $priority);
	}

	/**
	 *
	 * Add css to the page style view variable
	 *
	 * @access public
	 *
	 * @param string $style
	 * @param int $priority PAGE::PRIORITY_NORMAL
	 *
	 * @return Page
	 *
	 * #### Example
	 * ```php
	 * ci('page')->style('. { font-size: 9px }');
	 * ```
	 */
	public function style(string $style, int $priority = PAGE::PRIORITY_NORMAL) : Page
	{
		return $this->add('style', $style.PHP_EOL, $priority);
	}

	/**
	 *
	 * Add a script tag to the view variable
	 * <script ...></script>
	 *
	 * @access public
	 *
	 * @param string $script
	 * @param int $priority PAGE::PRIORITY_NORMAL
	 *
	 * @return Page
	 *
	 * #### Example
	 * ```php
	 * ci('page')->script('/assets/javascript.js');
	 * ci('page')->script('/assets/javascript.js',PAGE::PRIORITY_HIGHEST);
	 * ```
	 */
	public function js($file = '', int $priority = PAGE::PRIORITY_NORMAL) : Page
	{
		if (is_array($file)) {
			foreach ($file as $f) {
				$this->js($f, $priority);
			}
			return $this;
		}

		return $this->add('js', $this->script_html($file).PHP_EOL, $priority);
	}

	/**
	 *
	 * Add a link tag to the view variable
	 * <link ... />
	 *
	 * @access public
	 *
	 * @param $file
	 * @param int $priority PAGE::PRIORITY_NORMAL
	 *
	 * @return Page
	 *
	 * #### Example
	 * ```php
	 * ci('page')->css('/assets/application.cs');
	 * ```
	 */
	public function css($file = '', int $priority = PAGE::PRIORITY_NORMAL) : Page
	{
		if (is_array($file)) {
			foreach ($file as $f) {
				$this->css($f, $priority);
			}
			return $this;
		}

		return $this->add('css', $this->link_html($file).PHP_EOL, $priority);
	}

	/**
	 *
	 * Add a Javascript variable to the view variable
	 *
	 * @access public
	 *
	 * @param string $key
	 * @param $value
	 * @param int $priority PAGE::PRIORITY_NORMAL
	 * @param bool $raw false
	 *
	 * @return Page
	 *
	 * #### Example
	 * ```php
	 * ci('page')->js_variable('name','Johnny Appleseed');
	 * ci('page')->js_variable('name','{name: "Johnny Appleseed"}',PAGE::PRIORITY_NORMAL,true);
	 * ```
	 */
	public function js_variable(string $key, $value, int $priority = PAGE::PRIORITY_NORMAL, bool $raw = false) : Page
	{
		if ($raw) {
			$value = 'var '.$key.'='.$value.';' ;
		} else {
			$value = ((is_scalar($value)) ? 'var '.$key.'="'.str_replace('"', '\"', $value).'";' : 'var '.$key.'='.json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE).';');
		}

		return $this->add('js_variables', $value, $priority);
	}

	/**
	 *
	 * Add a Javascript variables to the view variable
	 *
	 * @access public
	 *
	 * @param array $array
	 *
	 * @throws
	 * @return Page
	 *
	 * #### Example
	 * ```php
	 * ci('page')->js_variables(['name'=>'Johnny','age'=>23]);
	 * ```
	 */
	public function js_variables(array $array) : Page
	{
		foreach ($array as $k => $v) {
			$this->js_variable($k, $v);
		}

		return $this;
	}

	/**
	 *
	 * Add a class variable to the body class view variable
	 *
	 * @access public
	 *
	 * @param $class
	 * @param int $priority PAGE::PRIORITY_NORMAL
	 *
	 * @return Page
	 *
	 * #### Example
	 * ```php
	 * ci('page')->body_class('body-wrapper');
	 * ci('page')->body_class('body-wrapper o-theme');
	 * ci('page')->body_class(['body-wrapper','o-theme']);
	 * ```
	 */
	public function body_class($class, int $priority = PAGE::PRIORITY_NORMAL) : Page
	{
		return (is_array($class)) ? $this->_body_class($class, $priority) : $this->_body_class(explode(' ', $class), $priority);
	}

	/**
	 *
	 * Add a variable to the view variables
	 *
	 * @access public
	 *
	 * @param string $name
	 * @param string $value
	 * @param int $priority PAGE::PRIORITY_NORMAL
	 * @param bool $prevent_duplicates true
	 *
	 * @return Page
	 *
	 * #### Example
	 * ```php
	 * ci('page')->add('custom_var','<p>Custom Stuff!</p>');
	 * ```
	 */
	public function add(string $name, string $value, int $priority = PAGE::PRIORITY_NORMAL, bool $prevent_duplicates = true) : Page
	{
		$key = md5($value);

		if (!isset($this->variables[$name][3][$key]) || !$prevent_duplicates) {
			$this->variables[$name][0] = !isset($this->variables[$name]); /* sorted */
			$this->variables[$name][1][] = (int)$priority; /* unix priority */
			$this->variables[$name][2][] = $value; /* actual html content (string) */
			$this->variables[$name][3][$key] = true; /* prevent duplicates */
		}

		return $this;
	}

	/**
	 *
	 * Wrapper for var because that is a Reserved Word and throws errors with some PHP analyzers
	 *
	 * @access public
	 *
	 * @param string $name
	 * @param array $arguments []
	 *
	 * @throws \Exception
	 * @return mixed
	 *
	 * #### Example
	 * ```php
	 * ci('page')->var('script');
	 * ```
	 */
	public function __call(string $name, array $arguments = [])
	{
		if ($name == 'var') {
			return $this->_var($arguments[0]);
		}

		throw new \Exception('Page Method '.$name.' unsupported.');
	}

	/**
	 *
	 * Retrieve a page variable (with "post" priority processing)
	 * included page variables: title, meta, body_class, css, style, js, script, js_variables, script, domready
	 *
	 * @access public
	 *
	 * @param string $name
	 *
	 * @return string
	 *
	 * #### Example
	 * ```php
	 * $script_html = ci('page')->var('script');
	 * ```
	 */
	public function _var(string $name) : string
	{
		$html = $this->load->get_var($name);

		/* if it's empty than maybe is it a page variable? */
		if (empty($html)) {
			$html = $this->load->get_var($this->page_variable_prefix.$name);
		}

		/* does this variable key exist */
		if (isset($this->variables[$name])) {
			/* has it already been sorted */
			if (!$this->variables[$name][0]) {
				/* no we must sort it */
				array_multisort($this->variables[$name][1], SORT_DESC, SORT_NUMERIC, $this->variables[$name][2]);

				/* mark it as sorted */
				$this->variables[$name][0] = true;
			}

			foreach ($this->variables[$name][2] as $append) {
				$html .= $append;
			}
		}

		return trim($html);
	}

	/**
	 *
	 * Internal body class handler
	 *
	 * @access protected
	 *
	 * @param array $class
	 * @param int $priority
	 *
	 * @return Page
	 *
	 */
	protected function _body_class(array $class, int $priority) : Page
	{
		foreach ($class as $c) {
			$this->add('body_class', ' '.strtolower(trim($c)), $priority);
		}

		return $this;
	}
} /* end page */
