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
 * @show Unified Error collecting class
 */
class Errors {
	protected $config;
	protected $load;
	protected $input;
	protected $output;
	protected $event;

	protected $errors_variable;
	protected $html_prefix;
	protected $html_suffix;
	protected $data_records;
	protected $data_count;

	public function __construct(&$config=[]) {
		$this->config = &$config;

		$this->load = &ci('load');
		$this->input = &ci('input');
		$this->output = &ci('output');
		$this->event = &ci('event');

		$this->errors_variable = $this->config['errors_variable'] ?? 'ci_errors';

		$this->html_prefix = $this->config['html_prefix'] ?? '<p class="orange error">';
		$this->html_suffix = $this->config['html_suffix'] ?? '</p>';

		$this->data_records = $this->config['data_records'] ?? 'records';
		$this->data_count = $this->config['data_count'] ?? 'count';
	}

	/**
	 * add
	 * Insert description here
	 *
	 * @param $msg
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function add($msg) {
		log_message('debug', 'Errors::add::'.$msg);

		/* get the current errors from the view data */
		$current_errors = $this->load->get_var($this->errors_variable);

		/* add this error */
		$current_errors[$msg] = $msg;

		/* put it back into the view data */
		$this->load->vars($this->errors_variable,$current_errors);

		/* chain-able */
		return $this;
	}

	/**
	 * clear
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
	public function clear() {
		/* empty out the view data */
		$this->load->vars($this->errors_variable,[]);

		/* chain-able */
		return $this;
	}

	/**
	 * has
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
	public function has() {
		/* do we have any errors? */
		return (count($this->load->get_var($this->errors_variable)) != 0);
	}

	/**
	 * as_array
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
	public function as_array() {
		/* return the errors as an array */
		return $this->load->get_var($this->errors_variable);
	}

	/**
	 * as_html
	 * Insert description here
	 *
	 * @param $prefix
	 * @param $suffix
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function as_html($prefix = null, $suffix = null) {
		$html = '';

		/* do we have any errors? */
		if ($this->has()) {
			/* get them from the view data */
			$errors = $this->load->get_var($this->errors_variable);

			/* if they didn't send in a default prefix then use ours */
			if ($prefix === null) {
				$prefix = $this->html_prefix;
			}

			/* if they didn't send in a default suffix then use ours */
			if ($suffix === null) {
				$suffix = $this->html_suffix;
			}

			/* format the output */
			foreach ($errors as $val) {
				if (!empty(trim($val))) {
					$html .= $prefix.trim($val).$suffix;
				}
			}
		}

		return $html;
	}

	/**
	 * as_cli
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
	public function as_cli() {
		/* return as string with tabs and line-feeds */
		return $this->as_html(chr(9),chr(10));
	}

	/**
	 * as_data
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
	public function as_data() {
		/* get them from the view data */
		$errors = $this->load->get_var($this->errors_variable);

		/* return as a array */
		return [$this->data_records => array_values($errors)] + [$this->data_count => count($errors)];
	}

	/**
	 * as_json
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
	public function as_json() {
		/* get the as data array and convert to json */
		return json_encode($this->as_data(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT);
	}

	/**
	 * show
	 * Insert description here
	 *
	 * @param $message
	 * @param $status_code
	 * @param $heading
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function show($message, $status_code, $heading = 'An Error Was Encountered') {
		/* show the errors */
		$this->display('general',['heading'=>$heading,'message'=>$message],$status_code);
	}

	/**
	 * display
	 * Insert description here
	 *
	 * @param $view
	 * @param $data
	 * @param $status_code
	 * @param $override
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function display($view, $data = [], $status_code = 500, $override = []) {
		if (is_numeric($view)) {
			$status_code = (int)$view;
		}

		/* setup the defaults */
		$view = (isset($this->config['named'][$view])) ? $this->config['named'][$view] : $view;
		$charset     = 'utf-8';
		$mime_type   = 'text/html';
		$view_folder = 'html';

		$data['heading'] = ($data['heading']) ? $data['heading'] : 'Fatal Error';
		$data['message'] = ($data['message']) ? $data['message'] : 'Unknown Error';

		if ($this->input->is_cli_request()) {
			/* if it's a cli request then output for cli */
			$view_folder = 'cli';
			$message     = '';

			foreach ($data as $key => $val) {
				$message .= '  '.$key.': '.strip_tags($val).chr(10);
			}

			$data['message'] = $message;
		} elseif ($this->input->is_ajax_request()) {
			/* if it's a ajax request then format for ajax (json) */
			$view_folder = 'ajax';
			$mime_type   = 'application/json';
		} else {
			/* else prepare for html */
			$data['message'] = $this->html_prefix.(is_array($data['message']) ? implode($this->html_suffix.$this->html_prefix, $data['message']) : $data['message']).$this->html_suffix;
			$view_folder     = 'html';
		}

		$charset     = ($override['charset']) ? $override['charset'] : $charset;
		$mime_type   = ($override['mime_type']) ? $override['mime_type'] : $mime_type;
		$view_folder = ($override['view_folder']) ? $override['view_folder'] : $view_folder;
		$view_path = 'errors/'.$view_folder.'/error_'.str_replace('.php', '', $view);
		$status_code = abs($status_code);

		if ($status_code < 100) {
			$exit_status = $status_code + 9;
			$status_code = 500;
		} else {
			$exit_status = 1;
		}

		log_message('error', 'Error: '.$view_path.' '.$status_code.' '.print_r($data,true));

		$this->event->trigger('death.show');

		$this->output
			->enable_profiler(false)
			->set_status_header($status_code)
			->set_content_type($mime_type, $charset)
			->set_output(view($view_path,$data))
			->_display()
			->exit($exit_status);
	}

} /* end class */
