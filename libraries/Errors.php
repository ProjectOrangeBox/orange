<?php
/**
 * Errors
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
 * core: load, input, output
 * libraries: event
 * models:
 * helpers:
 * functions:
 *
 * @help Unified Error collecting class
 */
class Errors {
	protected $config;
	protected $input;
	protected $output;
	protected $event;

	protected $flashdata_session_variable;
	protected $html_prefix;
	protected $html_suffix;

	protected $data_records;
	protected $data_count;

	protected $errors = [];
	protected $current_index;
	protected $default_index;
	protected $duplicates = [];
	protected $to_string = 'array';
	protected $forced_output = false;

	public function __construct(&$config=[])
	{
		$this->config = &$config;

		$this->input = &ci('input');
		$this->output = &ci('output');
		$this->event = &ci('event');

		$this->flashdata_session_variable = $this->config['flashdata session variable'] ?? 'ci_errors';

		$this->html_prefix = $this->config['html_prefix'] ?? '<p class="orange error">';
		$this->html_suffix = $this->config['html_suffix'] ?? '</p>';

		$this->default_index = $this->config['default error group'] ?? 'records';
		$this->current_index = $this->default_index;
	}

	public function __toString()
	{
		log_message('debug', 'Errors::__toString');
		
		return $this->get();
	}
	
	public function get_group()
	{
		return $this->current_index;
	}

	public function group($index = null)
	{
		$index = ($index) ? $index : $this->default_index;

		$this->current_index = $index;

		log_message('debug', 'Errors::group::'.$this->current_index);

		return $this;
	}

	public function as($to_string)
	{
		log_message('debug', 'Errors::as::'.$to_string);

		$this->to_string = $to_string;

		return $this;
	}

	public function get()
	{
		log_message('debug', 'Errors::get');

		switch($this->to_string) {
			case 'html':
				$output = $this->as_html();
			break;
			case 'cli':
				$output = $this->as_cli();
			break;
			case 'json':
				$output = $this->as_json();
			break;
			default:
				$output = $this->as_array();
		}

		return $output;
	}

	/**
	 * add
	 */
	public function add($msg,$index=null)
	{
		$index = ($index) ? $index : $this->current_index;

		log_message('debug', 'Errors::add::'.$msg.' '.$index);

		$dup_key = md5($index.$msg);

		if (!isset($this->duplicates[$dup_key])) {
			$this->errors[$index][] = $msg;

			$this->duplicates[$dup_key] = true;
		}

		/* chain-able */
		return $this;
	}

	/**
	 * clear
	 */
	public function clear($index=null)
	{
		$index = ($index) ? $index : $this->current_index;

		log_message('debug', 'Errors::clear::'.$index);

		$this->errors[$index] = [];

		/* chain-able */
		return $this;
	}

	/**
	 * has
	 */
	public function has($index=null)
	{
		$index = ($index) ? $index : $this->current_index;
	
		$has = (bool)count($this->errors[$index]);

		log_message('debug', 'Errors::has::'.$index.' '.$has);

		/* do we have any errors? */
		return $has;
	}

	/**
	 * redirect to another page on error
	 */
	public function redirect_on_error($url = null,$wallet_status = 'red',$index = null)
	{
		log_message('debug', 'Errors::redirect_on_error '.$url.' '.$wallet_status.' '.$index);

		if ($this->has($index)) {
			if ($wallet_status) {
				ci('wallet')->msg($this->as_html(null,null,$index),$wallet_status,((is_string($url)) ? $url : true));
			} else {
				ci('session')->set_flashdata($this->flashdata_session_variable,$this->as_array($index));
				
				/* did they send in a URL? if not use the referrer page */
				$redirect_url = (is_string($url) ? $url : $this->input->server('HTTP_REFERER'));

				redirect($redirect_url);
			}
		}

		return $this;
	}

	/**
	 * show error view on error and die
	 */
	public function die_on_error($view = 400, $index = null)
	{
		log_message('debug', 'Errors::die_on_error::'.$view.' '.$index);

		if ($this->has($index)) {
			$this->display($view);
		}

		return $this;
	}

	/**
	 * as_array
	 */
	public function as_array($index=null)
	{
		log_message('debug', 'Errors::as_array::'.$index);
		
		/* multiple groups? */
		if (is_string($index)) {
			if (strpos($index,',') !== false) {
				/* multiple */
				$multiple = [];
	
				foreach(explode(',',$index) as $m) {
					$m = trim($m);
				
					$multiple[$m] = $this->errors[$m];
				}
	
				return $multiple;
			} else {
				return $this->errors[$index];
			}
		}

		return $this->errors;
	}

	/**
	 * as_html
	 */
	public function as_html($prefix = null, $suffix = null, $index = null)
	{
		log_message('debug', 'Errors::as_html::'.$index);

		$html = '';

		/* do we have any errors? */
		if ($this->has($index)) {
			/* if they didn't send in a default prefix then use ours */
			if ($prefix === null) {
				$prefix = $this->html_prefix;
			}

			/* if they didn't send in a default suffix then use ours */
			if ($suffix === null) {
				$suffix = $this->html_suffix;
			}

			/* format the output */
			foreach ($this->as_array($index) as $grouping=>$errors) {
				if (is_array($errors)) {
					foreach ($errors as $val) {
						if (!empty(trim($val))) {
							$html .= $this->insert_into_first_class($prefix,$grouping).trim($val).$suffix;
						}
					}
				} else {
					if (!empty(trim($errors))) {
						$html .= $prefix.trim($errors).$suffix;
					}
				}
			}
		}

		return $html;
	}

	/**
	 * as_cli
	 */
	public function as_cli($index = null)
	{
		log_message('debug', 'Errors::as_cli::'.$index);

		/* return as string with tabs and line-feeds */
		return json_encode($this->as_array($index),JSON_PRETTY_PRINT).PHP_EOL;
	}

	/**
	 * as_json
	 */
	public function as_json($index = null)
	{
		log_message('debug', 'Errors::as_json::'.$index);

		return json_encode($this->as_array($index), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT);
	}

	/**
	 * show
	 */
	public function show($message, $status_code, $heading = 'An Error Was Encountered')
	{
		/* show the errors */
		$this->display('general',['heading'=>$heading,'message'=>$message],$status_code);
	}

	public function input($type)
	{
		log_message('debug', 'Errors::input::'.$type);

		$this->forced_output = $type;

		return $this;
	}

	/**
	 * display
	 * display error view and exit
	 *
	 */
	public function display($view, $data = [], $status_code = 500, $override = [])
	{
		log_message('debug', 'Errors::view::'.$view.' '.$status_code);

		if (is_numeric($view)) {
			$status_code = (int)$view;
		}

		if ($this->forced_output) {
			$output_format = $this->forced_output;
		} else {
			if ($this->input->is_cli_request()) {
				$output_format = 'cli';
			} elseif ($this->input->is_ajax_request()) {
				$output_format = $this->input->is_ajax_request();
			}
		}

		/* remap the view to another based on it's name */
		$view = (isset($this->config['named'][$view])) ? $this->config['named'][$view] : $view;

		$data['heading'] = ($data['heading']) ?? 'Fatal Error '.$status_code;
		$data['message'] = ($data['message']) ?? 'Unknown Error';

		switch ($output_format) {
			case 'cli':
				$this->as('cli');
				$view_folder = 'cli';
			break;
			case 'ajax':
				$this->as('json');
				$view_folder = 'ajax';
				$mime_type   = 'application/json';
			break;
			default:
				$this->as('html');
				$view_folder = 'html';
				$mime_type = 'text/html';
				$charset = 'utf-8';
		}

		$view_folder = ($override['view_folder']) ? $override['view_folder'] : $view_folder;
		$view_path = $view_folder.'/error_'.str_replace('.php', '', $view);

		/* get "as" using __toString */
		$data['message'] = (string)$this;

		$charset     = ($override['charset']) ?? $charset;
		$mime_type   = ($override['mime_type']) ?? $mime_type;

		$status_code = abs($status_code);

		log_message('debug', 'Errors::display '.$status_code.' '.$mime_type.' '.$charset.' '.$view_path);

		if ($status_code < 100) {
			$exit_status = $status_code + 9;
			$status_code = 500;
		} else {
			$exit_status = 1;
		}

		log_message('error', 'Error: '.$view_path.' '.$status_code.' '.json_encode($data));

		$this->event->trigger('death.show',$view_path,$data);

		$this->output
			->enable_profiler(false)
			->set_status_header($status_code)
			->set_content_type($mime_type, $charset)
			->set_output($this->error_view($view_path,$data))
			->_display();

		$this->output->exit($exit_status);
	}

	/* add this here to cut down on external functions */
	protected function error_view($_view,$_data=[])
	{
		log_message('debug', 'Errors::error_view::'.$_view);

		/* clean up the view path */
		$_file = APPPATH.'views/errors/'.$_view.'.php';

		/* get a list of all the found views */
		if (!file_exists($_file)) {
			/* Not Found */
			die('Could not locate error view "'.$_file.'"');
		}

		/* import variables into the current symbol table from an only prefix invalid/numeric variable names with _ 	*/
		extract($_data, EXTR_PREFIX_INVALID, '_');

		/* turn on output buffering */
		ob_start();

		/* bring in the view file */
		include $_file;

		/* return the current buffer contents and delete current output buffer */
		return ob_get_clean();
	}
	
	protected function insert_into_first_class($html,$class)
	{
		if (preg_match('/class="([^=]*)"/',$html, $matches, PREG_OFFSET_CAPTURE, 0)) {
			$html = substr_replace($html,$class.' ',$matches[1][1], 0);
		}

		return $html;
	}

} /* end class */
