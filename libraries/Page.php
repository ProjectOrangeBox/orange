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
class Page {
	protected $variables = [];
	protected $prevent_duplicate = [];
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
			foreach ($page_configs as $method=>$parms) {
				if (method_exists($this,$method)) {
					if (is_array($parms)) {
						call_user_func_array([$this,$method],$parms);
					} else {
						call_user_func([$this,$method],$parms);
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
	* @param $_view_file string
	* @param $_data array
	* @param $_return mixed
	*
	* @return mixed
	*
	*/
	public function view($_view_file = null, $_data = null, $_return = true) {
		$_data = (is_array($_data)) ? 	array_merge($this->load->get_vars(),$_data) : $this->load->get_vars();

		/* call core orange function view() */
		$_buffer = view($_view_file,$_data);

		if (is_string($_return)) {
			$this->data($_return,$_buffer);
		}

		return ($_return === true) ? $_buffer : $this;
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
	public function extend($template=null) {
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
	public function meta($attr, $name, $content = null,$priority = 50) {
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
	public function script($script,$priority = 50) {
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
	public function domready($script,$priority = 50) {
		return $this->add('domready',$script.PHP_EOL,$priority);
	}

	/**
	* title
	* <title>SkyNet</title>
	*
	* @param $title
	*
	* @return $this
	*
	*/
	public function title($title = '',$priority = 50) {
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
	public function style($style,$priority = 50) {
		return $this->add('style',$style.PHP_EOL,$priority);
	}

	/**
	* js
	* <script src="//ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
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

		return $this->add('js',$this->script_html($file).PHP_EOL,$priority);
	}

	/**
	* css
	* <link href="//maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet">
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
	public function tag($name,$attributes,$content = false,$priority = 50) {
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
	public function js_variable($key,$value,$priority = 50,$raw=false) {
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
	public function body_class($class,$priority = 50) {
		return (is_array($class)) ? $this->_body_class($class,$priority) : $this->_body_class(explode(' ',$class));
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
	public function add($name,$value,$priority = 50,$prevent_duplicates = true) {
		$key = md5($value);

		if (!isset($this->prevent_duplicate[$key]) || !$prevent_duplicates) {
			$this->prevent_duplicate[$key] = true;

			$this->variables[$name][(int)$priority][] = $value;
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
		return $this->_prepare_page_variable($this->variables[$name],$this->load->get_var($this->page_variable_prefix.$name));
	}

	/* protected */

	protected function _prepare_page_variable($priority_queue,$content) {
		if (is_array($priority_queue)) {
			ksort($priority_queue);

			/* add the currently available entries */
			foreach ($priority_queue as $priority) {
				foreach ($priority as $string) {
					$content .= $string;
				}
			}
		}

		return $content;
	}

	protected function _body_class($class,$priority = 50) {
		foreach ($class as $c) {
			$this->add('body_class',strtolower($c).' ',$priority);
		}

		return $this;
	}

} /* end page */
