<?php
/*
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
	protected $prepend_asset = false;
	protected $route;
	protected $page_prefix = 'page_';
	protected $assets            = [];
	protected $script_attributes = ['src' => '', 'type' => 'text/javascript', 'charset' => 'utf-8'];
	protected $link_attributes   = ['href' => '', 'type' => 'text/css', 'rel' => 'stylesheet'];
	protected $domready_javascript = 'document.addEventListener("DOMContentLoaded",function(e){%%});';

	public function __construct() {
		$this->route = strtolower(trim(ci()->router->fetch_directory().ci()->router->fetch_class(true).'/'.ci()->router->fetch_method(true), '/'));

		if (isset(ci()->user)) {
			$userid = md5(ci()->user->id.config('config.encryption_key'));
			$this->body_class(str_replace('/',' ',$this->route).(ci()->user->is_active ? ' active' : ' not-active').' uid'.$userid);
			$this->data('user', ci()->user);
		} else {
			$userid = 'guest';
			$this->body_class(str_replace('/',' ',$this->route).' not-active uid'.$userid);
		}

		require 'Pear.php';

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
			if ($config = config('page.'.$mc,null)) {
				$this->$mc($config);
			}
		}

		$this
			->js_variables([
				'base_url'            => $base_url,
				'app_id'              => md5($base_url),
				'controller_path'     => '/'.str_replace('/index', '', $this->route),
				'user_id'             => $userid,
			])
			->data([
				'controller'        => ci()->controller,
				'controller_path'   => ci()->controller_path,
				'controller_title'  => ci()->controller_title,
				'controller_titles' => ci()->controller_titles,
			]);

		//$this->title(((config('page.title')) ? config('page.title') : 'Web Application'));

		log_message('info', 'Page Class Initialized');
	}

	public function title($title = '') {
		return $this->data($this->page_prefix.'title', $title);
	}

	public function meta($attr, $name, $content = null) {
		return $this->_asset_add('meta','<meta '.$attr.'="'.$name.'"'.(($content) ? ' content="'.$content.'"' : '').'>');
	}

	public function body_class($class) {
		$this->assets['body_class'] .= ' '.preg_replace('/[^\da-z ]/i', '', strtolower($class));

		return $this->data($this->page_prefix.'body_class',trim(implode(' ',array_unique(explode(' ',$this->assets['body_class'])))));
	}

	public function render($view = null, $data = []) {
		$view = ($view) ? $view : str_replace('-', '_', $this->route);

		event::trigger('page.render', $this, $view);

		event::trigger('page.render.'.str_replace('/','.',$view),$this, $view);

		$view_content = $this->view($view, $data);

		if (pear::is_extending()) {
			$view_content = $this->view(pear::is_extending());
		}

		event::trigger('page.render.content',$view_content);

		ci()->output->append_output($view_content);

		return $this;
	}

	public function view($_view_file = null, $_data = [], $_return = true) {
		$_buffer = trim(view($_view_file,array_merge(ci()->load->get_vars(),$_data)));

		if (is_string($_return)) {
			ci()->load->vars([$_return => $_buffer]);
		}

		return ($_return === true) ? $_buffer : $this;
	}

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

	public function icon($image_path = '') {
		return $this->data($this->page_prefix.'icon', '<link rel="icon" type="image/x-icon" href="'.$image_path.'"><link rel="apple-touch-icon" href="'.$image_path.'">');
	}

	public function css($file = '') {
		if (is_array($file)) {
			foreach ($file as $f) {
				$this->css($f);
			}
			return $this;
		}

		return $this->_asset_add('css',$this->link_html($file));
	}

	public function link_html($file) {
		return $this->ary2element('link', array_merge($this->link_attributes, ['href' => $file]));
	}

	public function style($style) {
		return $this->_asset_add('style',$style);
	}

	public function js($file = '') {
		if (is_array($file)) {
			foreach ($file as $f) {
				$this->js($f);
			}
			return $this;
		}

		return $this->_asset_add('js',$this->script_html($file));
	}

	public function script_html($file) {
		return $this->ary2element('script', array_merge($this->script_attributes, ['src' => $file]), '');
	}

	public function js_variable($key,$value) {
		return $this->_asset_add('js_variables',((is_scalar($value)) ? 'var '.$key.'="'.str_replace('"', '\"', $value).'";' : 'var '.$key.'='.json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE).';'));
	}

	public function js_variables($array) {
		foreach ($array as $k => $v) {
			$this->js_variable($k, $v);
		}

		return $this;
	}

	public function script($script) {
		return $this->_asset_add('script',$script);
	}

	public function domready($script) {
		return $this->_asset_add('domready',$script);
	}

	public function ary2element($element, $attributes, $wrapper = false) {
		$output = '<'.$element.' '.$this->convert2attributes($attributes);

		return ($wrapper === false) ? $output.'/>' : $output.'>'.$wrapper.'</'.$element.'>';
	}

	public function convert2attributes($attributes,$prefix='') {
		foreach ($attributes as $name => $value) {
			if (!empty($value)) {
				$output .= $prefix.$name.'="'.trim($value).'" ';
			}
		}

		return trim($output);
	}

	public function prepend_asset($bol = true) {
		$this->prepend_asset = $bol;
	}

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

} /* end file */
