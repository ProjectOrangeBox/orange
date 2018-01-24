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

class Errors {
	protected static $errors_variable = 'ci_errors';

	public static function add($msg) {
		log_message('debug', 'Errors::add::'.$msg);

		$current_errors = ci()->load->get_var(self::$errors_variable);
		$current_errors[$msg] = $msg;

		ci()->load->vars(self::$errors_variable, $current_errors);
	}

	public static function clear() {
		ci()->load->vars(self::$errors_variable, []);
	}

	public static function has() {
		return (count(ci()->load->get_var(self::$errors_variable)) != 0);
	}

	public static function as_array() {
		return ci()->load->get_var(self::$errors_variable);
	}

	public static function as_html($prefix = null, $suffix = null) {
		$str = '';

		if (self::has()) {
			$errors = ci()->load->get_var(self::$errors_variable);
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

	public static function as_cli() {
		return errors::as_html(chr(9), chr(10));
	}

	public static function as_data() {
		$errors = ci()->load->get_var(self::$errors_variable);

		return ['records' => array_values($errors)] + ['count' => count($errors)];
	}

	public static function as_json() {
		return json_encode(self::as_data(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT);
	}

	public static function show($message, $status_code, $heading = 'An Error Was Encountered') {
		self::display('general',['heading'=>$heading,'message'=>$message],$status_code);
	}

	public static function display($view, $data = [], $status_code = 500, $override = []) {
		if (is_numeric($view)) {
			$status_code = (int) $view;
		}

		$config = config('errors');

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
		event::trigger('death.show');

		ci()->output
			->enable_profiler(false)
			->set_status_header($status_code)
			->set_content_type($mime_type, $charset)
			->set_output(view($view_path,$data))
			->_display();

		exit($exit_status);
	}

} /* end file */
