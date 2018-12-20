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
	protected $input;
	protected $output;
	protected $event;

	protected $errors_variable;
	protected $html_prefix;
	protected $html_suffix;
	protected $data_records;
	protected $data_count;

	protected $errors = [];

	public function __construct(&$config=[]) {
		$this->config = &$config;

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
	 * redirect to another page on error
	 */
	public function redirect_on_error($url = null,$wallet_status='red') {
		if ($this->has()) {
			if ($wallet_status) {
				ci('wallet')->msg($this->as_html(),$wallet_status,((is_string($url)) ? $url : true));
			} else {
				ci('session')->set_flashdata($this->errors_variable,$this->as_array());

				redirect((is_string($url) ? $url : $this->input->server('HTTP_REFERER')));
			}
		}

		return $this;
	}

	/**
	 * show error view on error and die
	 */
	public function die_on_error($view = 400) {
		if ($this->has()) {
			$this->display($view);
		}

		return $this;
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
	public function add($msg,$index=null) {
		log_message('debug', 'Errors::add::'.$msg);

		if ($index) {
			$this->errors[$index] = $msg;
		} else {
			$this->errors[$this->data_records][] = $msg;
			$this->errors[$this->data_count] = count($this->errors[$this->data_records]);
		}

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
		$this->errors = [];

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
		return (count($this->errors[$this->data_count]) != 0);
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
		return $this->errors;
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
			$errors = $this->as_array();

			/* if they didn't send in a default prefix then use ours */
			if ($prefix === null) {
				$prefix = $this->html_prefix;
			}

			/* if they didn't send in a default suffix then use ours */
			if ($suffix === null) {
				$suffix = $this->html_suffix;
			}

			/* format the output */
			foreach ($errors[$this->data_records] as $val) {
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
		return trim(str_replace('Array'.PHP_EOL,PHP_EOL,print_r($this->as_array(),true))).PHP_EOL;
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
		return json_encode($this->as_array(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT);
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
	public function show($message, $status_code, $heading = 'An Error Was Encountered')
	{
		/* show the errors */
		$this->display('general',['heading'=>$heading,'message'=>$message],$status_code);
	}

	/**
	 * display
	 * display error view and exit
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
	public function display($view, $data = [], $status_code = 500, $override = [])
	{
		if (is_numeric($view)) {
			$status_code = (int)$view;
		}

		/* setup the defaults */
		$view = (isset($this->config['named'][$view])) ? $this->config['named'][$view] : $view;

		$data['heading'] = ($data['heading']) ?? 'Fatal Error '.$status_code;
		$data['message'] = ($data['message']) ?? 'Unknown Error';

		if ($this->input->is_cli_request() || $override['input'] == 'cli') {
			$view_folder = 'cli';
		} elseif ($this->input->is_ajax_request() || $override['input'] == 'ajax') {
			$view_folder = 'ajax';
		} else {
			$view_folder = 'html';
		}

		$charset     = 'utf-8';
		$mime_type   = 'text/html';

		$view_folder = ($override['view_folder']) ? $override['view_folder'] : $view_folder;
		$view_path = $view_folder.'/error_'.str_replace('.php', '', $view);

		switch ($view_folder) {
			case 'cli':
				$this->add($view_path,'_template');

				$data['message'] = $this->as_cli();
			break;
			case 'ajax':
				$mime_type   = 'application/json';

				$this->add($view_path,'_template');

				$data['message'] = $this->as_json();
			break;
			default:
				$data['message'] = $this->as_html();
		}

		$charset     = ($override['charset']) ?? $charset;
		$mime_type   = ($override['mime_type']) ?? $mime_type;

		$status_code = abs($status_code);

		if ($status_code < 100) {
			$exit_status = $status_code + 9;
			$status_code = 500;
		} else {
			$exit_status = 1;
		}

		log_message('error', 'Error: '.$view_path.' '.$status_code.' '.print_r($data,true));

		$this->event->trigger('death.show',$view_path,$data);

		$complete_output = $this->error_view($view_path,$data);

		if (strpos($complete_output,'</html>') !== false) {
			$complete_output = str_replace('</html>','<!--APPPATH/views/errors/'.$view_path.'--></html>',$complete_output);
		}

		$this->output
			->enable_profiler(false)
			->set_status_header($status_code)
			->set_content_type($mime_type, $charset)
			->set_output($complete_output)
			->_display();

		$this->output->exit($exit_status);
	}

	protected function error_view($_view,$_data=[])
	{
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
