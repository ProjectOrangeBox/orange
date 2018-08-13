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
 * core: load, output
 * libraries: event
 * models:
 * helpers:
 * functions:
 * constants: PAGE_MIN
 *
 * @ used but not required
 */
class Page {
	protected $variables = [];
	protected $prevent_duplicate = [];
	protected $route;

	protected $config;
	protected $load;
	protected $output;
	protected $event;

	protected $page_variable_prefix;
	protected $extending = false;

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
	public function __construct(&$config) {
		$this->config = &$config;

		$this->load = &ci('load');
		$this->output = &ci('output');
		$this->event = &ci('event');

		define('PAGE_MIN',(env('SERVER_DEBUG') == 'development' ? '' : '.min'));

		$this->page_variable_prefix = ($this->config['page_prefix']) ?? 'page_';

		$page_configs = $this->config[$this->page_variable_prefix];

		if (is_array($page_configs)) {
			foreach ($page_configs as $key=>$value) {
				if (method_exists($this,$key)) {
					$this->$key($value);
				}
			}
		}

		log_message('info', 'Page Class Initialized');
	}

	public function route($route) {
		$this->route = $route;

		return $this;
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
		return $this->data($this->page_variable_prefix.'title',$title);
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
	public function meta($attr, $name, $content = null,$priority = 50) {
		return $this->add($this->page_variable_prefix.'meta','<meta '.$attr.'="'.$name.'"'.(($content) ? ' content="'.$content.'"' : '').'>'.PHP_EOL,$priority);
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
	public function body_class($class,$priority = 50) {
		if (is_string($class)) {
			if (strpos($class,' ') !== false) {
				$class = explode(' ',$class);
			}
		}

		if (is_array($class)) {
			foreach ($class as $c) {
				$this->body_class($c,$priority);
			}
			return $this;
		}

		return $this->add($this->page_variable_prefix.'body_class',strtolower($class).' ',$priority);
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

		$view = ($view) ? $view : str_replace('-', '_',$this->route);

		$this->event->trigger('page.render',$this,$view);
		$this->event->trigger('page.render.'.str_replace('/','.',$view),$this,$view);

		$this->data($data);

		/* this is going to be the "main" section */
		$view_content = $this->view($view);

		if ($this->extending) {
			$view_content = $this->view($this->extending);
		}

		$this->event->trigger('page.render.content',$view_content,$view,$data);

		$this->output->append_output($view_content);

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
		$_buffer = view($_view_file,array_merge($this->load->get_vars(),(array)$_data));

		if (is_string($_return)) {
			$this->data($_return,$_buffer);
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
		$this->load->vars($name,$value);

		return $this;
	}


	public function extend($template=null) {
		if ($this->extending) {
			throw new Exception('You are already extending "'.$this->extending.'" therefore we cannot extend "'.$name.'".');
		}

		$this->extending = $template;

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
		return $this->data($this->page_variable_prefix.'icon', '<link rel="icon" type="image/x-icon" href="'.$image_path.'"><link rel="apple-touch-icon" href="'.$image_path.'">');
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
	public function css($file = '',$priority = 50) {
		if (is_array($file)) {
			foreach ($file as $f) {
				$this->css($f,$priority);
			}
			return $this;
		}

		return $this->add($this->page_variable_prefix.'css',$this->link_html($file).PHP_EOL,$priority);
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
		return $this->ary2element('link', array_merge($this->config['link_attributes'], ['href' => $file]));
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
	public function style($style,$priority = 50) {
		return $this->add($this->page_variable_prefix.'style',$style.PHP_EOL,$priority);
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
	public function js($file = '',$priority = 50) {
		if (is_array($file)) {
			foreach ($file as $f) {
				$this->js($f,$priority);
			}
			return $this;
		}

		return $this->add($this->page_variable_prefix.'js',$this->script_html($file).PHP_EOL,$priority);
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
		return $this->ary2element('script', array_merge($this->config['script_attributes'], ['src' => $file]),'');
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
	public function js_variable($key,$value,$priority = 50,$raw=false) {
		if ($raw) {
			$value = 'var '.$key.'='.$value.';' ;
		} else {
			$value = ((is_scalar($value)) ? 'var '.$key.'="'.str_replace('"', '\"', $value).'";' : 'var '.$key.'='.json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE).';');
		}

		return $this->add($this->page_variable_prefix.'js_variables',$value,$priority);
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
	public function script($script,$priority = 50) {
		return $this->add($this->page_variable_prefix.'script',$script.PHP_EOL,$priority);
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
	public function domready($script,$priority = 50) {
		return $this->add($this->page_variable_prefix.'domready',$script.PHP_EOL,$priority);
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
		$output = '<'.$element._stringify_attributes($attributes);

		return ($wrapper === false) ? $output.'/>' : $output.'>'.$wrapper.'</'.$element.'>';
	}

/**
 * This prepares the current page variables
 *
 * @return $this
 *
 */
	public function prepare_page_variables() {
		foreach ($this->variables as $page_variable=>$priorityqueue) {
			ksort($priorityqueue);

			/* get the current content */
			$current_content = $this->load->get_var($page_variable);

			/* add the currently available entries */
			foreach ($priorityqueue as $priority) {
				foreach ($priority as $string) {
					$current_content .= $string;
				}
			}

			/* load back into the view variable */
			$this->load->vars($page_variable,$current_content);

			unset($this->variables[$page_variable]);
		}

		return $this;
	}

/**
 * add element
 *
 * @param $name
 * @param $value
 * @param $priority
 *
 * @return $this
 *
 */
	public function add($name,$value,$priority=50) {
		$key = md5($value);

		if (!isset($this->prevent_duplicate[$key])) {
			$this->prevent_duplicate[$key] = true;

			$this->variables[$name][(int)$priority][] = $value;
		}

		return $this;
	}

} /* end page */
