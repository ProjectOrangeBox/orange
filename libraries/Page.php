<?php
/**
 * Page
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
 * core: router, load, output
 * libraries: event, user@
 * models:
 * helpers: url
 * functions:
 *
 * @ used but not required
 */
class Page {
	/**
	 * track if the combined cached configuration has been loaded
	 *
	 * @var boolean
	 */
	protected $prepend_asset = false;

	/**
	 * track if the combined cached configuration has been loaded
	 *
	 * @var boolean
	 */
	protected $route;

	/**
	 * track if the combined cached configuration has been loaded
	 *
	 * @var boolean
	 */
	protected $page_prefix = 'page_';

	/**
	 * track if the combined cached configuration has been loaded
	 *
	 * @var boolean
	 */
	protected $assets            = [];

	/**
	 * track if the combined cached configuration has been loaded
	 *
	 * @var boolean
	 */
	protected $assets_added			 = [];

	/**
	 * track if the combined cached configuration has been loaded
	 *
	 * @var boolean
	 */
	protected $script_attributes = ['src' => '', 'type' => 'text/javascript', 'charset' => 'utf-8'];

	/**
	 * track if the combined cached configuration has been loaded
	 *
	 * @var boolean
	 */
	protected $link_attributes   = ['href' => '', 'type' => 'text/css', 'rel' => 'stylesheet'];

	/**
	 * track if the combined cached configuration has been loaded
	 *
	 * @var boolean
	 */
	protected $domready_javascript = 'document.addEventListener("DOMContentLoaded",function(e){%%});';

/**
 * __construct
 * Insert description here
 *
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function __construct() {
		$this->route = strtolower(trim(ci()->router->fetch_directory().ci()->router->fetch_class(true).'/'.ci()->router->fetch_method(true), '/'));		
		$controller_path = '/'.str_replace('/index', '', $this->route);
		$this->body_class(str_replace('/',' uri-',$controller_path));
		$uid = 'guest';
		$is = 'not-active';
		if (isset(ci()->user)) {
			$uid = md5(ci()->user->id.config('config.encryption_key'));
			if (ci()->user->logged_in()) {
				$is = 'active';
			}
			$this->data('user', ci()->user);
		}
		$this->body_class(['uid-'.$uid,'is-'.$is]);
		ci()->load->helper('url');
		$base_url = trim(base_url(), '/');
		$merge_configs = [
			'title',
			'body_class',
			'data',
			'css',
			'js',
			'script',
			'style',
			'domready',
			'js_variables',
			'icon',
		];
		foreach ($merge_configs as $mc) {
			if ($config = config('page.'.$mc,false)) {
				$this->$mc($config);
			}
		}
		$this
			->js_variables([
				'base_url'            => $base_url,
				'app_id'              => md5($base_url),
				'controller_path'     => $controller_path,
				'user_id'             => $uid,
			]);
		log_message('info', 'Page Class Initialized');
	}

/**
 * title
 * Insert description here
 *
 * @param $title
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function title($title = '') {
		return $this->data($this->page_prefix.'title', $title);
	}

/**
 * meta
 * Insert description here
 *
 * @param $attr
 * @param $name
 * @param $content
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function meta($attr, $name, $content = null) {
		return $this->_asset_add('meta','<meta '.$attr.'="'.$name.'"'.(($content) ? ' content="'.$content.'"' : '').'>');
	}

/**
 * body_class
 * Insert description here
 *
 * @param $class
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function body_class($class) {
		if (is_array($class)) {
			foreach ($class as $c) {
				$this->body_class($c);
			}
			return $this;
		}
		$normalized = trim(preg_replace('/[^\da-z -]/i', '', strtolower($class)));
		$this->assets['body_class'][$normalized] = $normalized;
		return $this->data($this->page_prefix.'body_class',implode(' ',$this->assets['body_class']));
	}

/**
 * render
 * Insert description here
 *
 * @param $view
 * @param $data
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function render($view = null, $data = []) {
		log_message('debug', 'page::render::'.$view);
		$view = ($view) ? $view : str_replace('-', '_', $this->route);
		ci('event')->trigger('page.render',$this,$view);
		ci('event')->trigger('page.render.'.str_replace('/','.',$view),$this,$view);
		$view_content = $this->view($view, $data);
		if (pear::is_extending()) {
			$view_content = $this->view(pear::is_extending());
		}
		ci('event')->trigger('page.render.content',$view_content,$view,$data);
		ci()->output->append_output($view_content);
		return $this;
	}

/**
 * view
 * Insert description here
 *
 * @param $_view_file
 * @param $_data
 * @param $_return
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function view($_view_file = null, $_data = [], $_return = true) {
		$_buffer = trim(view($_view_file,array_merge(ci()->load->get_vars(),$_data)));
		if (is_string($_return)) {
			ci()->load->vars([$_return => $_buffer]);
		}
		return ($_return === true) ? $_buffer : $this;
	}

/**
 * data
 * Insert description here
 *
 * @param $name
 * @param $value
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function data($name = null, $value = null) {
		if (is_array($name)) {
			foreach ($name as $k => $v) {
				$this->data($k, $v);
			}
			return $this;
		}
		ci()->load->vars([$name => $value]);
		return $this;
	}

/**
 * icon
 * Insert description here
 *
 * @param $image_path
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function icon($image_path = '') {
		return $this->data($this->page_prefix.'icon', '<link rel="icon" type="image/x-icon" href="'.$image_path.'"><link rel="apple-touch-icon" href="'.$image_path.'">');
	}

/**
 * css
 * Insert description here
 *
 * @param $file
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function css($file = '') {
		if (is_array($file)) {
			foreach ($file as $f) {
				$this->css($f);
			}
			return $this;
		}
		return $this->_asset_add('css',$this->link_html($file));
	}

/**
 * link_html
 * Insert description here
 *
 * @param $file
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function link_html($file) {
		return $this->ary2element('link', array_merge($this->link_attributes, ['href' => $file]));
	}

/**
 * style
 * Insert description here
 *
 * @param $style
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function style($style) {
		return $this->_asset_add('style',$style);
	}

/**
 * js
 * Insert description here
 *
 * @param $file
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function js($file = '') {
		if (is_array($file)) {
			foreach ($file as $f) {
				$this->js($f);
			}
			return $this;
		}
		return $this->_asset_add('js',$this->script_html($file));
	}

/**
 * script_html
 * Insert description here
 *
 * @param $file
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function script_html($file) {
		return $this->ary2element('script', array_merge($this->script_attributes, ['src' => $file]), '');
	}

/**
 * js_variable
 * Insert description here
 *
 * @param $key
 * @param $value
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function js_variable($key,$value) {
		return $this->_asset_add('js_variables',((is_scalar($value)) ? 'var '.$key.'="'.str_replace('"', '\"', $value).'";' : 'var '.$key.'='.json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE).';'));
	}

/**
 * js_variables
 * Insert description here
 *
 * @param $array
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function js_variables($array) {
		foreach ($array as $k => $v) {
			$this->js_variable($k, $v);
		}
		return $this;
	}

/**
 * script
 * Insert description here
 *
 * @param $script
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function script($script) {
		return $this->_asset_add('script',$script);
	}

/**
 * domready
 * Insert description here
 *
 * @param $script
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function domready($script) {
		return $this->_asset_add('domready',$script);
	}

/**
 * ary2element
 * Insert description here
 *
 * @param $element
 * @param $attributes
 * @param $wrapper
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function ary2element($element, $attributes, $wrapper = false) {
		$output = '<'.$element.' '.$this->convert2attributes($attributes);
		return ($wrapper === false) ? $output.'/>' : $output.'>'.$wrapper.'</'.$element.'>';
	}

/**
 * convert2attributes
 * Insert description here
 *
 * @param $attributes
 * @param $prefix
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function convert2attributes($attributes,$prefix='') {
		foreach ($attributes as $name => $value) {
			if (!empty($value)) {
				$output .= $prefix.$name.'="'.trim($value).'" ';
			}
		}
		return trim($output);
	}

/**
 * prepend_asset
 * Insert description here
 *
 * @param $bol
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function prepend_asset($bol = true) {
		$this->prepend_asset = $bol;
	}

/**
 * _asset_add
 * Insert description here
 *
 * @param $name
 * @param $value
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	protected function _asset_add($name,$value) {
		$key = md5($value);
		if (!isset($this->assets_added[$key])) {
			$this->assets_added[$key] = true;
			$complete_name = $this->page_prefix.$name;
			if ($this->prepend_asset) {
				ci()->load->vars([$complete_name => $value.chr(10).ci()->load->get_var($complete_name)]);
			} else {
				ci()->load->vars([$complete_name => ci()->load->get_var($complete_name).$value.chr(10)]);
			}
		}
		return $this;
	}
}
