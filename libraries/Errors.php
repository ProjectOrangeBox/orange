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
	protected $html_group_class;

	protected $data_records;
	protected $data_count;

	protected $errors = [];
	protected $current_group;
	protected $default_group;
	protected $duplicates = [];
	protected $request_type = 'array';

	public function __construct(&$config=[])
	{
		$this->config = &$config;

		$this->input = &ci('input');
		$this->output = &ci('output');
		$this->event = &ci('event');

		$this->flashdata_session_variable = $this->config['flashdata session variable'] ?? 'ci_errors';

		$this->html_prefix = $this->config['html_prefix'] ?? '<p class="{group class} orange-errors">';
		$this->html_suffix = $this->config['html_suffix'] ?? '</p>';
		$this->html_group_class = $this->config['html_group_class'] ?? '{group class}';

		$this->default_group = $this->config['default error group'] ?? 'records';
		$this->current_group = $this->default_group;

		if ($this->config['auto detect']) {
			if ($this->input->is_cli_request()) {
				$this->set_request_type('cli');
			} elseif ($this->input->is_ajax_request()) {
				$this->set_request_type('ajax');
			} else {
				$this->set_request_type('html');
			}
		}
	}

	/**
	 *
	 * For when you cast the object to a string
	 *
	 */
	public function __toString()
	{
		log_message('debug', 'Errors::__toString');

		return $this->get();
	}

	public function get_default_group()
	{
		return $this->default_group;
	}

	public function get_group()
	{
		return $this->current_group;
	}

	public function group($group)
	{
		$this->current_group = $group;

		log_message('debug', 'Errors::group::'.$this->current_group);

		return $this;
	}

	/* wrapper for as */
	public function set_request_type($request_type)
	{
		return $this->as($request_type);
	}

	public function as($request_type)
	{
		log_message('debug', 'Errors::as::'.$request_type);

		/* options include cli, ajax, html */
		if (!in_array($request_type,['cli','ajax','json','html','array'])) {
			throw new Exception(__METHOD__.' unknown type '.$request_type.'.');
		}

		$this->request_type = $request_type;

		return $this;
	}

	public function get()
	{
		log_message('debug', 'Errors::get');

		switch($this->request_type) {
			case 'html':
				$output = $this->as_html();
			break;
			case 'cli':
				$output = $this->as_cli();
			break;
			case 'ajax':
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
	public function add($msg,$fieldname=null)
	{
		log_message('debug', 'Errors::add::'.$msg.' '.$this->current_group);

		$dup_key = md5($this->current_group.$msg.$fieldname);

		if (!isset($this->duplicates[$dup_key])) {
			if ($fieldname) {
				$this->errors[$this->current_group][$fieldname] = $msg; /* field based keys */
			} else {
				$this->errors[$this->current_group][] = $msg; /* number based keys auto incremented */
			}

			$this->duplicates[$dup_key] = true;
		}

		/* chain-able */
		return $this;
	}

	/**
	 * clear
	 */
	public function clear($group=null)
	{
		$group = ($group) ? $group : $this->current_group;

		log_message('debug', 'Errors::clear::'.$group);

		$this->errors[$group] = [];

		/* chain-able */
		return $this;
	}

	/**
	 * has
	 */
	public function has($group=null)
	{
		$group = ($group) ? $group : $this->current_group;

		$has = (bool)count($this->errors[$group]);

		log_message('debug', 'Errors::has::'.$group.' '.$has);

		/* do we have any errors? */
		return $has;
	}


	/**
	 * show error view on error and die
	 */
	public function die_on_error($view = 400, $group = null)
	{
		$group = ($group) ? $group : $this->current_group;

		log_message('debug', 'Errors::die_on_error::'.$view.' '.$group);

		if ($this->has($group)) {
			$this->display($view);
		}

		return $this;
	}

	/**
	 * as_array
	 */
	public function as_array($group=null)
	{
		log_message('debug', 'Errors::as_array::'.$group);

		$array = $this->errors;

		if ($group) {
			if (is_array($group)) {
				$groups = $group;
			} else {
				$groups = explode(',',$group);
			}

			if (count($groups) > 1) {
				/* multi leveled */
				$multiple = [];

				foreach($groups as $m) {
					$m = trim($m);

					$multiple[$m] = $this->errors[$m];
				}

				$array = $multiple;
			} else {
				/* not multi leveled */
				$array = [$groups[0]=>$this->errors[$groups[0]]];
			}
		}

		return $array;
	}

	/**
	 * as_html
	 */
	public function as_html($prefix = null, $suffix = null, $group = null)
	{
		log_message('debug', 'Errors::as_html::'.$group);

		$errors = $this->as_array($group);

		$html = '';

		/* do we have any errors? */
		if (count($errors)) {
			/* if they didn't send in a default prefix then use ours */
			if ($prefix === null) {
				$prefix = $this->html_prefix;
			}

			/* if they didn't send in a default suffix then use ours */
			if ($suffix === null) {
				$suffix = $this->html_suffix;
			}

			/* format the output */
			foreach ($this->as_array($group) as $grouping=>$errors) {
				if (is_array($errors)) {
					foreach ($errors as $val) {
						if (!empty(trim($val))) {
							$html .= str_replace($this->html_group_class,'error-group-'.$grouping,$prefix.trim($val).$suffix);
						}
					}
				} else {
					if (!empty(trim($errors))) {
						$html .= str_replace($this->html_group_class,'error-group-'.$grouping,$prefix.trim($errors).$suffix);
					}
				}
			}
		}

		return $html;
	}

	/**
	 * as_cli
	 */
	public function as_cli($group = null)
	{
		log_message('debug', 'Errors::as_cli::'.$group);

		/* return as string with tabs and line-feeds */
		return json_encode($this->as_array($group),JSON_PRETTY_PRINT).PHP_EOL;
	}

	/**
	 * as_json
	 */
	public function as_json($group = null)
	{
		log_message('debug', 'Errors::as_json::'.$group);

		return json_encode($this->as_array($group), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT);
	}

	/**
	 * show
	 */
	public function show($message, $status_code, $heading = 'An Error Was Encountered')
	{
		/* show the errors */
		$this->display('general',['heading'=>$heading,'message'=>$message],$status_code);
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

		if ($this->request_type) {
			$output_format = $this->request_type;
		} else {
			if ($this->input->is_cli_request()) {
				$output_format = 'cli';
			} elseif ($this->input->is_ajax_request()) {
				$output_format = 'ajax';
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
			case 'json':
			case 'ajax':
				$this->as('ajax');
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

	/**
	 *
	 * add this here to cut down on external functions
	 *
	 */
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

} /* end class */
