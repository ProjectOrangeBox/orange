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
 * core:
 * libraries:
 * models:
 * helpers:
 * functions:
 *
 */
class Page {
	protected $prepend_asset = false; /* when adding plugins they are added before current view variable content */

	protected $route; /* used for auto loading of views */
	protected $page_prefix = 'page_'; /* page view variable prefix */

	protected $assets            = []; /* combined js and css storage */
	protected $script_attributes = ['src' => '', 'type' => 'text/javascript', 'charset' => 'utf-8'];
	protected $link_attributes   = ['href' => '', 'type' => 'text/css', 'rel' => 'stylesheet'];

	/* this is the default also configurable in config page->domready */
	protected $domready_javascript = 'document.addEventListener("DOMContentLoaded",function(e){%%});';

	public function __construct() {
		$this->route = strtolower(trim(ci()->router->fetch_directory() . ci()->router->fetch_class(true) . '/' . ci()->router->fetch_method(true), '/'));

		if (isset(ci()->user)) {
			$userid = md5(ci()->user->id . config('config.encryption_key'));

			/* add the route to the body class */
			$this->body_class(str_replace('/',' ',$this->route) . (ci()->user->is_active ? ' active' : ' not-active') . ' uid' . $userid);

			$this->data('user', ci()->user);
		} else {
			$userid = 'guest';

			$this->body_class(str_replace('/',' ',$this->route) . ' not-active uid' . $userid);
		}

		ci()->load->helper('url');

		$base_url = trim(base_url(), '/');

		/* add a few more */
		$this
			->js_variables((array)config('page.js_variables') + [
				'base_url'            => $base_url,
				'app_id'              => md5($base_url),
				'controller_path'     => '/' . str_replace('/index', '', $this->route),
				'user_id'             => $userid,
			], true)
			->data((array)config('page.data') + [
				'controller'        => ci()->controller,
				'controller_path'   => ci()->controller_path,
				'controller_title'  => ci()->controller_title,
				'controller_titles' => ci()->controller_titles,
			]);

		$this->title(((config('page.title')) ? config('page.title') : 'Web Application'));

		log_message('info', 'Page Class Initialized');
	}

	/**
	 *	Header Functions
	 */

	/**
	 * title function.
	 *
	 * @access public
	 * @param string $title (default: '')
	 * @return void
	 */
	public function title($title = '') {
		$this->data($this->page_prefix . 'title', $title);

		/* chain-able */
		return $this;
	}

	/* add meta tag
	$this->page->meta('http-equiv','X-UA-Compatible','IE=edge,chrome=1');
	$this->page->meta('name','viewport','width=device-width,initial-scale=1');
	$this->page->meta('name','viewport','width=device-width,initial-scale=1');
	$this->page->meta('charset','utf-8');
	 */
	/**
	 * meta function.
	 *
	 * @access public
	 * @param mixed $attr
	 * @param mixed $name
	 * @param mixed $content (default: null)
	 * @return void
	 */
	public function meta($attr, $name, $content = null) {
		$content = ($content) ? ' content="' . $content . '"' : '';

		$meta = '<meta ' . $attr . '="' . $name . '"' . $content . '>';

		return $this->_asset_add('meta',$meta);
	}

	/*
	add a class to the page body tag
	this is great for css name spacing among other things.
	filters for repeats

	$this->page->body_class('name');
	$this->page->body_class('name name2 name3');
	 */
	/**
	 * body_class function.
	 *
	 * @access public
	 * @param mixed $class
	 * @return void
	 */
	public function body_class($class) {
		/* remove anything not alpha or a space and replace with a space then remove any run of spaces */
		$class = preg_replace('/[^\da-z ]/i', '', strtolower($class));

		$this->assets['body_class'][$class] = $class;

		$this->data($this->page_prefix . 'body_class', implode(' ', $this->assets['body_class']));

		return $this;
	}

	/**
	 * building methods
	 */

	/* final output - fires a event */
	/**
	 * render function.
	 *
	 * @access public
	 * @param mixed $view (default: null)
	 * @param mixed $data (default: [])
	 * @return void
	 */
	public function render($view = null, $data = []) {
		$view = ($view) ? $view : str_replace('-', '_', $this->route);

		/* anyone need to process something before build? */
		event::trigger('page.render', $this, $view);

		/* more specific */
		event::trigger('page.render.'.str_replace('/','.',$view),$this, $view);

		/* build this view */
		$view_content = $this->view($view, $data);

		/* Are they extending another view? */
		if (pear::is_extending()) {
			$view_content = $this->view(pear::is_extending());
		}

		ci()->output->append_output($view_content);

		return $this;
	}

	/**
	 * view function.
	 *
	 * @access public
	 * @param mixed $_view_file (default: null)
	 * @param mixed $_data (default: [])
	 * @param bool $_return (default: true)
	 * @return void
	 */
	public function view($_view_file = null, $_data = [], $_return = true) {
		$_buffer = o::view($_view_file,array_merge(ci()->load->get_vars(),$_data));

		if (is_string($_return)) {
			ci()->load->vars([$_return => $_buffer]);
		}

		return ($_return === true) ? $_buffer : $this;
	}

	/**
	 * data function.
	 *
	 * @access public
	 * @param mixed $name (default: null)
	 * @param mixed $value (default: null)
	 * @return void
	 */
	public function data($name = null, $value = null, $append = false) {
		/* if $name is a array then it a array of name/value pairs */
		if (is_array($name)) {
			foreach ($name as $k => $v) {
				$this->data($k, $v);
			}

			return $this;
		}

		if ($append) {
			$value .= ci()->load->get_var($name).$value;
		}

		ci()->load->vars([$name => $value]);

		return $this;
	}

	/**
	 *	 Asset Management
	 */

	/**
	 * icon function.
	 *
	 * @access public
	 * @param string $image_path (default: '')
	 * @return void
	 */
	public function icon($image_path = '') {
		$this->data($this->page_prefix . 'icon', '<link rel="icon" type="image/x-icon" href="' . $image_path . '"><link rel="apple-touch-icon" href="' . $image_path . '">');

		/* chain-able */
		return $this;
	}

	/*
	add a css file
	$this->page->css('/assets/style.css');
	$this->page->css('/assets/style.css',1);
	$this->page->css(['/assets/style.css','/assets/style2.css'],1);
	$this->page->css('http://www.example.com/style.css');
	$link = $this->page->css('http://www.example.com/style.css',true);
	 */
	/**
	 * css function.
	 *
	 * @access public
	 * @param string $file (default: '')
	 * @return void
	 */
	public function css($file = '') {
		/* handle it if it's a array */
		if (is_array($file)) {
			foreach ($file as $f) {
				$this->css($f);
			}

			return $this;
		}

		return $this->_asset_add('css',$this->link_html($file));
	}

	/**
	 * link_html function.
	 *
	 * @access public
	 * @param mixed $file
	 * @return void
	 */
	public function link_html($file) {
		return $this->ary2element('link', array_merge($this->link_attributes, ['href' => $file]));
	}

	/* place inside <style> */
	/**
	 * style function.
	 *
	 * @access public
	 * @param mixed $style
	 * @return void
	 */
	public function style($style) {
		return $this->_asset_add('style',$style);
	}

	/*
	add js file
	$this->page->js('/assets/site.js');
	$this->page->js('/assets/site.js',1);
	$this->page->js(['/assets/site.js','/assets/site2.js'],78);
	$script = $this->page->js('/assets/sites.js',true);
	 */
	/**
	 * js function.
	 *
	 * @access public
	 * @param string $file (default: '')
	 * @return void
	 */
	public function js($file = '') {
		/* handle it if it's a array */
		if (is_array($file)) {
			foreach ($file as $f) {
				$this->js($f);
			}

			return $this;
		}

		return $this->_asset_add('js',$this->script_html($file));
	}

	/**
	 * script_html function.
	 *
	 * @access public
	 * @param mixed $file
	 * @return void
	 */
	public function script_html($file) {
		return $this->ary2element('script', array_merge($this->script_attributes, ['src' => $file]), '');
	}

	/**
	 * js_variables function.
	 *
	 * @access public
	 * @param mixed $key
	 * @param mixed $value (default: null)
	 * @return void
	 */
	public function js_variables($key, $value = null) {
		/* handle it if it's a array */
		if (is_array($key) && $value === true) {
			foreach ($key as $k => $v) {
				$this->js_variables($k, $v);
			}

			return $this;
		}

		/* if value is true then insert the key verbatim else insert it as a string */
		if (is_scalar($value)) {
			$var = 'var ' . $key . '="' . str_replace('"', '\"', $value) . '";';
		} else {
			$var = 'var ' . $key . '=' . json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . ';';
		}

		return $this->_asset_add('js_variables',$var);
	}

	/* place inside <script> */
	/**
	 * script function.
	 *
	 * @access public
	 * @param mixed $script
	 * @return void
	 */
	public function script($script) {
		return $this->_asset_add('script',$script);
	}

	/**
	 * domready function.
	 *
	 * @access public
	 * @param mixed $script
	 * @return void
	 */
	public function domready($script) {
		return $this->_asset_add('domready',$script);
	}

	/**
	 * ary2element function.
	 *
	 * @access protected
	 * @param mixed $element
	 * @param mixed $attributes
	 * @param bool $wrapper (default: false)
	 * @return void
	 */
	public function ary2element($element, $attributes, $wrapper = false) {
		$output = '<' . $element . ' ' . $this->convert2attributes($attributes);

		return ($wrapper === false) ? $output . '/>' : $output . '>' . $wrapper . '</' . $element . '>';
	}


	/* HTML Functions */
	public function convert2attributes($attributes,$prefix='') {
		foreach ($attributes as $name => $value) {
			if (!empty($value)) {
				$output .= $prefix.$name . '="' . trim($value) . '" ';
			}
		}

		return trim($output);
	}

	public function prepend_asset($bol = true) {
		$this->prepend_asset = $bol;
	}

	# +-+-+-+-+-+-+-+-+-+
	# |p|r|o|t|e|c|t|e|d|
	# +-+-+-+-+-+-+-+-+-+

	protected function _asset_add($name,$value) {
		$key = md5($value);

		if (!isset($this->assets_added[$key])) {
			$this->assets_added[$key] = true;

			$complete_name = $this->page_prefix . $name;

			/* if we are adding plugins they come before on page content */
			if ($this->prepend_asset) {
				ci()->load->vars([$complete_name => $value.chr(10).ci()->load->get_var($complete_name)]);
			} else {
				ci()->load->vars([$complete_name => ci()->load->get_var($complete_name).$value.chr(10)]);
			}
		}

		return $this;
	}

} /* end class */
