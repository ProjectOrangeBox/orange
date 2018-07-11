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
 * libraries: event, pear, user@
 * models:
 * helpers: url
 * functions:
 *
 * @ used but not required
 */
class Page {
	protected $priority = 50;
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
	protected $variables = [];

	/**
	 * track if the combined cached configuration has been loaded
	 *
	 * @var boolean
	 */
	protected $prevent_duplicate = [];

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
		define('PAGE_MIN',(env('SERVER_DEBUG') == 'development' ? '' : '.min'));

		$this->route = strtolower(trim(ci('router')->fetch_directory().ci('router')->fetch_class(true).'/'.ci('router')->fetch_method(true), '/'));
		$controller_path = '/'.str_replace('/index', '', $this->route);
		$this->body_class(trim(str_replace('/',' uri-',$controller_path)));

		$uid = 'guest';
		$is = 'not-active';

		/* this is a variable test */
		if (isset(ci()->user)) {
			$uid = md5(ci('user')->id.config('config.encryption_key'));
			if (ci('user')->logged_in()) {
				$is = 'active';
			}
			$this->data('user', ci('user'));
		}

		$this->body_class(['uid-'.$uid,'is-'.$is]);
		
		/* used in plugins and views */
		ci('load')->helper('url');

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

		$this->js_variables([
			'base_url'				=> $base_url,
			'app_id'					=> md5($base_url),
			'controller_path' => $controller_path,
			'user_id'					=> $uid,
		]);

		log_message('info', 'Page Class Initialized');
	}

/**
 * title
 * Insert description here
 *
 * @param $title
 *
 * @return $this
 *
 */
	public function title($title = '') {
		return $this->data($this->page_prefix.'title', $title);
	}

/**
 * This prepares the current page variables
 *
 * @return $this
 *
 */
	public function prepare_page_variables() {
		foreach ($this->variables as $page_variable=>$entries) {
			/* sort the keys (priority) */
			ksort($entries);

			/* get the current content */
			$current_content = ci('load')->get_var($page_variable);

			/* add the currently available entries */
			foreach ($entries as $priority) {
				foreach ($priority as $string) {
					$current_content .= $string;
				}
			}

			ci('load')->vars($page_variable,$current_content);

			/* now flush those assets since they have already been added to the page variables */
			$this->variables = [];
		}

		return $this;
	}

/**
 * meta
 * Insert description here
 *
 * @param $attr
 * @param $name
 * @param $content
 * @param $priority integer
 *
 * @return $this
 *
 */
	public function meta($attr, $name, $content = null,$priority = null) {
		return $this->_asset_add('meta','<meta '.$attr.'="'.$name.'"'.(($content) ? ' content="'.$content.'"' : '').'>',$priority);
	}

/**
 * body_class
 * Insert description here
 *
 * @param $class
 * @param $priority integer
 *
 * @return $this
 *
 */
	public function body_class($class,$priority = null) {
		if (is_array($class)) {
			foreach ($class as $c) {
				$this->body_class($c,$priority);
			}
			return $this;
		}

		return $this->_asset_add('body_class',' '.strtolower($class),$priority);
	}

/**
 * render
 * Insert description here
 *
 * @param $view string
 * @param $data array
 *
 * @return $this
 *
 */
	public function render($view = null, $data = []) {
		log_message('debug', 'page::render::'.$view);

		$view = ($view) ? $view : str_replace('-', '_', $this->route);

		ci('event')->trigger('page.render',$this,$view);
		ci('event')->trigger('page.render.'.str_replace('/','.',$view),$this,$view);

		/* this is going to be the "main" section */
		$view_content = $this->view($view, $data);

		/* Are they using pear ? */
		if (class_exists('pear',false)) {
			$is_extending = pear::is_extending();

			if ($is_extending) {
				$view_content = $this->view($is_extending);
			}
		}

		ci('event')->trigger('page.render.content',$view_content,$view,$data);

		ci('output')->append_output($view_content);

		return $this;
	}

/**
 * view
 * Insert description here
 *
 * @param $_view_file string
 * @param $_data array
 * @param $_return mixed
 *
 * @return mixed
 *
 */
	public function view($_view_file = null, $_data = [], $_return = true) {
		$this->prepare_page_variables();

		/* call core orange function view() */
		$_buffer = view($_view_file,array_merge(ci('load')->get_vars(),(array)$_data));

		if (is_string($_return)) {
			ci('load')->vars([$_return => $_buffer]);
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
 * @return $this
 *
 */
	public function data($name = null, $value = null) {
		if (is_array($name)) {
			foreach ($name as $k => $v) {
				ci('load')->vars($k,$v);
			}
			return $this;
		}

		ci('load')->vars($name,$value);

		return $this;
	}

/**
 * icon
 * Insert description here
 *
 * @param $image_path
 *
 * @return $this
 *
 */
	public function icon($image_path = '') {
		return $this->data($this->page_prefix.'icon', '<link rel="icon" type="image/x-icon" href="'.$image_path.'"><link rel="apple-touch-icon" href="'.$image_path.'">');
	}

/**
 * css
 * Insert description here
 *
 * @param $file
 * @param $priority integer
 *
 * @return $this
 *
 */
	public function css($file = '',$priority = null) {
		if (is_array($file)) {
			foreach ($file as $f) {
				$this->css($f,$priority);
			}
			return $this;
		}

		return $this->_asset_add('css',$this->link_html($file),$priority);
	}

/**
 * link_html
 * Insert description here
 *
 * @param $file
 *
 * @return string
 *
 */
	public function link_html($file) {
		return $this->ary2element('link', array_merge($this->link_attributes, ['href' => $file]));
	}

/**
 * style
 * Insert description here
 *
 * @param $style
 * @param $priority integer
 *
 * @return $this
 *
 */
	public function style($style,$priority = null) {
		return $this->_asset_add('style',$style,$priority);
	}

/**
 * js
 * Insert description here
 *
 * @param $file
 * @param $priority integer
 *
 * @return $this
 *
 */
	public function js($file = '',$priority = null) {
		if (is_array($file)) {
			foreach ($file as $f) {
				$this->js($f,$priority);
			}
			return $this;
		}

		return $this->_asset_add('js',$this->script_html($file),$priority);
	}

/**
 * script_html
 * Insert description here
 *
 * @param $file
 *
 * @return string
 *
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
 * @param $priority integer
 *
 * @return $this
 *
 */
	public function js_variable($key,$value,$priority = null,$raw=false) {
		if ($raw) {
			$value = 'var '.$key.'='.$value.';' ;
		} else {
			$value = ((is_scalar($value)) ? 'var '.$key.'="'.str_replace('"', '\"', $value).'";' : 'var '.$key.'='.json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE).';');
		}

		return $this->_asset_add('js_variables',$value,$priority);
	}

/**
 * js_variables
 * Insert description here
 *
 * @param $array
 *
 * @return $this
 *
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
 * @param $priority integer
 *
 * @return $this
 *
 */
	public function script($script,$priority = null) {
		return $this->_asset_add('script',$script,$priority);
	}

/**
 * domready
 * Insert description here
 *
 * @param $script
 * @param $priority integer
 *
 * @return $this
 *
 */
	public function domready($script,$priority = null) {
		return $this->_asset_add('domready',$script,$priority);
	}

/**
 * ary2element
 *
 * @param $element
 * @param $attributes
 * @param $wrapper
 *
 * @return string
 *
 */
	public function ary2element($element, $attributes, $wrapper = false) {
		$output = '<'.$element.' '.$this->convert2attributes($attributes);

		return ($wrapper === false) ? $output.'/>' : $output.'>'.$wrapper.'</'.$element.'>';
	}

/**
 * convert2attributes
 *
 * @param $attributes
 * @param $prefix
 * @param $strip_empty boolean
 *
 * @return string
 *
 */
	public function convert2attributes($attributes,$prefix='',$strip_empty=true) {
		foreach ($attributes as $name => $value) {
			if (!empty($value) || !$strip_empty) {
				$output .= $prefix.$name.'="'.trim($value).'" ';
			}
		}

		return trim($output);
	}

/**
 * set_priority
 *
 * @param $priority integer
 *
 * @return $this
 *
 */
	public function set_priority($priority) {
		$this->priority = (int)$priority;

		return $this;
	}

/**
 * reset_priority
 *
 * @return $this
 *
 */
	public function reset_priority() {
		$this->priority = 50;

		return $this;
	}

/**
 * Insert description here
 *
 * @param $name
 * @param $value
 * @param $priority
 *
 * @return $this
 *
 */
	protected function _asset_add($name,$value,$priority=null) {
		$priority = ($priority) ? $priority : $this->priority;

		$key = md5($value);

		if (!isset($this->prevent_duplicate[$key])) {
			$this->prevent_duplicate[$key] = true;

			$this->variables[$this->page_prefix.$name][$priority][] = $value;
		}

		return $this;
	}

} /* end page */
