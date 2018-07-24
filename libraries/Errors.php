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
	/**
	 * track if the combined cached configuration has been loaded
	 *
	 * @var boolean
	 */
	protected $errors_variable = 'ci_errors';

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
		$current_errors = ci('load')->get_var( $this->errors_variable);
		$current_errors[$msg] = $msg;
		ci('load')->vars( $this->errors_variable, $current_errors);

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
		ci('load')->vars( $this->errors_variable, []);

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
		return (count(ci('load')->get_var( $this->errors_variable)) != 0);
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
		return ci('load')->get_var( $this->errors_variable);
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
		$str = '';

		if ( $this->has()) {
			$errors = ci('load')->get_var( $this->errors_variable);
			if ($prefix === null) {
				$prefix = '<p class="orange error">';
			}
			if ($suffix === null) {
				$suffix = '</p>';
			}
			foreach ($errors as $val) {
				if (!empty(trim($val))) {
					$str .= $prefix.trim($val).$suffix;
				}
			}
		}

		return $str;
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
		return $this->as_html(chr(9), chr(10));
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
		$errors = ci('load')->get_var( $this->errors_variable);

		return ['records' => array_values($errors)] + ['count' => count($errors)];
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
		return json_encode( $this->as_data(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT);
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
			$status_code = (int) $view;
		}

		$config = config('errors',[]);
		$view = ($config['named'][$view]) ? $config['named'][$view] : $view;
		$charset     = 'utf-8';
		$mime_type   = 'text/html';
		$view_folder = 'html';
		$data['heading'] = ($data['heading']) ? $data['heading'] : 'Fatal Error';
		$data['message'] = ($data['message']) ? $data['message'] : 'Unknown Error';

		if (ci()->input->is_cli_request()) {
			$view_folder = 'cli';
			$message     = '';
			foreach ($data as $key => $val) {
				$message .= '  '.$key.': '.strip_tags($val).chr(10);
			}
			$data['message'] = $message;
		} elseif (ci()->input->is_ajax_request()) {
			$view_folder = 'ajax';
			$mime_type   = 'application/json';
		} else {
			$data['message'] = '<p>'.(is_array($data['message']) ? implode('</p><p>', $data['message']) : $data['message']).'</p>';
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

		ci('event')->trigger('death.show');

		ci()->output
			->enable_profiler(false)
			->set_status_header($status_code)
			->set_content_type($mime_type, $charset)
			->set_output(view($view_path,$data))
			->_display();
		exit($exit_status);
	}

} /* end class */
