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
 * functions: view
 * constants: PAGE_MIN
 *
 */

/* Follows Linux Priority negative values are higher priority and positive values are lower priority */
define('EVENT_PRIORITY_LOWEST', 1);
define('EVENT_PRIORITY_LOW', 20);
define('EVENT_PRIORITY_NORMAL', 50);
define('EVENT_PRIORITY_HIGH', 80);
define('EVENT_PRIORITY_HIGHEST', 100);

class Page {
	protected $variables = [];
	protected $default_template = '';

	protected $config;
	protected $load;
	protected $output;
	protected $event;

	protected $page_variable_prefix;
	protected $extending = false;

	/**
	 * __construct
	 *
	 */
	public function __construct(&$config=[]) {
		$this->config = &$config;

		$this->load = &ci('load');
		$this->output = &ci('output');
		$this->event = &ci('event');

		/* if it's true then use the default else use what's in page_min config */
		define('PAGE_MIN',(($this->config['page_min'] === true) ? '.min' : $this->config['page_min']));

		$this->page_variable_prefix = ($this->config['page_prefix']) ?? 'page_';

		$page_configs = $this->config[$this->page_variable_prefix];

		if (is_array($page_configs)) {
			foreach ($page_configs as $method=>$parameters) {
				if (method_exists($this,$method)) {
					if (is_array($parameters)) {
						foreach ($parameters as $p) {
							call_user_func([$this,$method],$p);
						}
					} else {
						call_user_func([$this,$method],$parameters);
					}
				}
			}
		}

		log_message('info', 'Page Class Initialized');
	}

	/**
	 * set_default_template
	 *
	 * @param $template
	 *
	 * @return $this
	 *
	 */
	public function set_default_template($template='') {
		/* convert to file system safe */
		$this->default_template = $template;

		return $this;
	}

	/**
	 * render
	 * basic view rendering
	 * with:
	 * default template if none included
	 * event triggering
	 * optionally extend another view
	 *
	 * @param $view string
	 * @param $data array
	 *
	 * @return $this
	 *
	 */
	public function render($view = null, $data = null) {
		log_message('debug', 'page::render::'.$view);

		$view = ($view) ?? $this->default_template;

		/* called everytime - use with caution */
		$this->event->trigger('page.render',$this,$view);

		/* called only when a trigger matches the view */
		$this->event->trigger('page.render.'.$view,$this,$view);

		/* this is going to be the "main" section */
		$view_content = $this->view($view,$data);

		if ($this->extending) {
			$view_content = $this->view($this->extending);
		}

		/* called everytime - use with caution  */
		$this->event->trigger('page.render.content',$view_content,$view,$data);

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
	public function view($view_file = null, $data = null, $return = true) {
		$data = (is_array($data)) ? array_merge($this->load->get_vars(),$data) : $this->load->get_vars();

		/* call core orange function view() */
		$buffer = view($view_file,$data);

		if (is_string($return)) {
			$this->data($return,$buffer);
		}

		return ($return === true) ? $buffer : $this;
	}

	/**
	 * data
	 * wrapper
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


	/**
	 * extended
	 * Insert description here
	 *
	 * @param $template
	 *
	 * @return $this
	 *
	 */
	public function extend($template = null) {
		if ($this->extending) {
			throw new Exception('You are already extending "'.$this->extending.'" therefore we cannot extend "'.$name.'".');
		}

		$this->extending = $template;

		return $this;
	}

	/**
	 * link_html
	 * create and return html link
	 * <link href="//maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet">
	 *
	 * @param $file
	 *
	 * @return string
	 *
	 */
	public function link_html($file) {
		return $this->ary2element('link', array_merge($this->config['link_attributes'],['href' => $file]));
	}

	/**
	 * script_html
	 * create and return html script
	 * <script src="//cdnjs.cloudflare.com/ajax/libs/handlebars.js/4.0.11/handlebars.min.js"></script>
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
	 * ary2element
	 * Insert description here
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
	 * meta
	 * <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
	 *
	 * @param $attr
	 * @param $name
	 * @param $content
	 * @param $priority integer
	 *
	 * @return $this
	 *
	 */
	public function meta($attr, $name, $content = null,$priority = EVENT_PRIORITY_NORMAL) {
		if (is_array($attr)) {
			extract($attr);
		}

		return $this->add('meta','<meta '.$attr.'="'.$name.'"'.(($content) ? ' content="'.$content.'"' : '').'>'.PHP_EOL,$priority);
	}

	/**
	 * script
	 * <script>*</script>
	 *
	 * @param $script
	 * @param $priority integer
	 *
	 * @return $this
	 *
	 */
	public function script($script,$priority = EVENT_PRIORITY_NORMAL) {
		return $this->add('script',$script.PHP_EOL,$priority);
	}

	/**
	 * domready
	 * <script>%%*%%</script>
	 *
	 * @param $script
	 * @param $priority integer
	 *
	 * @return $this
	 *
	 */
	public function domready($script,$priority = EVENT_PRIORITY_NORMAL) {
		return $this->add('domready',$script.PHP_EOL,$priority);
	}

	/**
	 * title
	 * <title>*</title>
	 *
	 * @param $title
	 *
	 * @return $this
	 *
	 */
	public function title($title = '',$priority = EVENT_PRIORITY_NORMAL) {
		return $this->add('title',$title,$priority);
	}

	/**
	 * style
	 * <style>*</style>
	 *
	 * @param $style
	 * @param $priority integer
	 *
	 * @return $this
	 *
	 */
	public function style($style,$priority = EVENT_PRIORITY_NORMAL) {
		return $this->add('style',$style.PHP_EOL,$priority);
	}

	/**
	 * js
	 * <script src="*"></script>
	 *
	 * @param $file
	 * @param $priority integer
	 *
	 * @return $this
	 *
	 */
	public function js($file = '',$priority = EVENT_PRIORITY_NORMAL) {
		if (is_array($file)) {
			foreach ($file as $f) {
				$this->js($f,$priority);
			}
			return $this;
		}

		return $this->add('js',$this->script_html($file).PHP_EOL,$priority);
	}

	/**
	 * css
	 * <link href="*" rel="stylesheet">
	 *
	 * @param $file
	 * @param $priority integer
	 *
	 * @return $this
	 *
	 */
	public function css($file = '',$priority = EVENT_PRIORITY_NORMAL) {
		if (is_array($file)) {
			foreach ($file as $f) {
				$this->css($f,$priority);
			}
			return $this;
		}

		return $this->add('css',$this->link_html($file).PHP_EOL,$priority);
	}

	/**
	 * tag
	 * Add any html tag to and page variable
	 *
	 * Unpaired html tag
	 * tag('link',['rel'=>'icon','type'=>'image/x-icon','href'=>'/asset/image.jpg'],50)
	 * <link rel="icon" type="image/x-icon" href="/asset/image.jpg"/>
	 *
	 * Paired html tag w/content
	 * element('p',['class'=>'highlight','id'=>'pid3'],'This is important!',50)
	 * <p class="highlight" id="pid3">This is important!</p>
	 *
	 */
	public function tag($name,$attributes,$content = false,$priority = EVENT_PRIORITY_NORMAL) {
		if (func_num_args() == 3 && is_integer($content)) {
			$priority = $content;
			$content = false;
		}

		return $this->add($name,$this->ary2element($name,$attributes,$content),$priority);
	}

	/**
	 * js_variable
	 * <script>*</script>
	 *
	 * @param $key
	 * @param $value
	 * @param $priority integer
	 *
	 * @return $this
	 *
	 */
	public function js_variable($key,$value,$priority = EVENT_PRIORITY_NORMAL,$raw = false) {
		if ($raw) {
			$value = 'var '.$key.'='.$value.';' ;
		} else {
			$value = ((is_scalar($value)) ? 'var '.$key.'="'.str_replace('"', '\"', $value).'";' : 'var '.$key.'='.json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE).';');
		}

		return $this->add('js_variables',$value,$priority);
	}

	/**
	 * js_variables
	 * <script>*</script>
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
	 * body_class
	 * class="*"
	 *
	 * @param $class
	 * @param $priority integer
	 *
	 * @return $this
	 *
	 */
	public function body_class($class,$priority = EVENT_PRIORITY_NORMAL) {
		return (is_array($class)) ? $this->_body_class($class,$priority) : $this->_body_class(explode(' ',$class),$priority);
	}

	/**
	 * add
	 * append to page variable with optional priority & duplicate prevention
	 *
	 * @param $name
	 * @param $value
	 * @param $priority default 50 the LOWER the number the higher priority
	 * @param $prevent_duplicates (True/False)
	 *
	 * @return $this
	 *
	 */
	public function add($name,$value,$priority = EVENT_PRIORITY_NORMAL,$prevent_duplicates = true) {
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
	 * var
	 * retrieve a page variable (with "post" priority processing)
	 * included page variables: title, meta, body_class, css, style, js, script, js_variables, script, domready
	 * any additional "tags"
	 *
	 * @param $name
	 *
	 * @return string
	 *
	 */
	public function var($name) {
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
				array_multisort($this->variables[$name][1],SORT_DESC,SORT_NUMERIC,$this->variables[$name][2]);

				/* mark it as sorted */
				$this->variables[$name][0] = true;
			}

			foreach ($this->variables[$name][2] as $append) {
				$html .= $append;
			}
		}

		return trim($html);
	}

	protected function _body_class($class,$priority) {
		foreach ($class as $c) {
			$this->add('body_class',' '.strtolower(trim($c)).' ',$priority);
		}

		return $this;
	}

} /* end page */
