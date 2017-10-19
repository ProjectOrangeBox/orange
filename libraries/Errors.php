<?php
/**
 * Orange Framework Extension
 *
 * @package	CodeIgniter / Orange
 * @author Don Myers
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 *
 * required
 * core: load, input, output
 * libraries:
 * models:
 * helpers:
 * functions:
 *
 * Static Library
 */

class Errors {
	protected static $errors_variable = 'ci_errors';

	/**
	 * add a error
	 * @author Don Myers
	 * @param string $msg error message to add the errors array
	 */
	public static function add($msg) {
		$current_errors = ci()->load->get_var(self::$errors_variable);

		$current_errors[$msg] = $msg;

		ci()->load->vars(self::$errors_variable, $current_errors);
	}

	/**
	 * flush all errors
	 * @author Don Myers
	 */
	public static function clear() {
		ci()->load->vars(self::$errors_variable, []);
	}

	/**
	 * check if there are any errors
	 * @author Don Myers
	 * @return bool
	 */
	public static function has() {
		return (count(ci()->load->get_var(self::$errors_variable)) != 0);
	}

	/**
	 * get all the errors as a array
	 * @author Don Myers
	 * @return array a array of all of the errors
	 */
	public static function as_array() {
		return ci()->load->get_var(self::$errors_variable);
	}

	/**
	 * get all the errors as html
	 * @author Don Myers
	 * @param  string [$prefix = null] prefix for each error
	 * @param  string [$suffix = null] suffix for each error
	 * @return string html for the errors
	 */
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

			/* Generate the message string */
			foreach ($errors as $val) {
				if (!empty(trim($val))) {
					$str .= $prefix . trim($val) . $suffix;
				}
			}
		}

		return $str;
	}

	/**
	 * get all the errors for the console
	 * @author Don Myers
	 * @return string command line version of the errors
	 */
	public static function as_cli() {
		return errors::as_html(chr(9), chr(10));
	}

	/**
	 * get all the errors as a detailed array
	 * @author Don Myers
	 * @return array array containing a array of records and a count of those records
	 */
	public static function as_data() {
		$errors = ci()->load->get_var(self::$errors_variable);

		return ['records' => array_values($errors)] + ['count' => count($errors)];
	}

	/**
	 * get all the errors as a json formatted array 
	 * @author Don Myers
	 * @return string json string containing a array of records and a count of those records
	 */
	public static function as_json() {
		return json_encode(self::as_data(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT);
	}

	/**
	 * provides a "general" error dialog like CodeIgniters built in show_error()
	 * self::display('general',['heading'=>'General Error','message'=>$txt]);
	 *
	 * @author Don Myers
	 * @param [[Type]] $txt [[Description]]
	 */
	public static function show($message, $status_code, $heading = 'An Error Was Encountered') {
		self::display('general',['heading'=>$heading,'message'=>$message],$status_code);
	}

	/**
	 * display a error to the user with auto detection of connection type and view 
	 * @author Don Myers
	 * @param string $view the view to load
	 * @param array [$data = []] the data to pass to the array
	 * @param integer [$status_code = 500] the html status code
	 * @param array [$override = []] additional data for charset, mime_type, view_folder
	 */
	public static function display($view, $data = [], $status_code = 500, $override = []) {
		/* if view is a number then use that as the http status code */
		if (is_numeric($view)) {
			$status_code = (int) $view;
		}
		
		/* load the config into a local variable for easy access */
		$config = config('errors');

		/* map a named view to something else */
		$view = ($config['named'][$view]) ? $config['named'][$view] : $view;

		/* defaults */
		$charset     = 'utf-8';
		$mime_type   = 'text/html';
		$view_folder = 'html';

		$data['heading'] = ($data['heading']) ? $data['heading'] : 'Fatal Error';
		$data['message'] = ($data['message']) ? $data['message'] : 'Unknown Error';

		if (ci()->input->is_cli_request()) {
			/* format the message for the console */
			$view_folder = 'cli';
			$message     = '';

			foreach ($data as $key => $val) {
				$message .= chr(9) . $key . ' ' . strip_tags($val) . chr(10);
			}

			$data['message'] = $message;
		} elseif (ci()->input->is_ajax_request()) {
			/* do ajax */
			$view_folder = 'ajax';
			$mime_type   = 'application/json';
		} else {
			/* format the message for the browser */
			$data['message'] = '<p>' . (is_array($data['message']) ? implode('</p><p>', $data['message']) : $data['message']) . '</p>';
			$view_folder     = 'html';
		}

		/* overrides sent in? */
		$charset     = ($override['charset']) ? $override['charset'] : $charset;
		$mime_type   = ($override['mime_type']) ? $override['mime_type'] : $mime_type;
		$view_folder = ($override['view_folder']) ? $override['view_folder'] : $view_folder;

		/* what is the view path? */
		$view_path = 'errors/' . $view_folder . '/error_' . str_replace('.php', '', $view);

		/* clean up the status code and setup the exit status code (taken from the CodeIgniter error handler) */
		$status_code = abs($status_code);

		if ($status_code < 100) {
			$exit_status = $status_code + 9; // 9 is EXIT__AUTO_MIN
			$status_code = 500;
		} else {
			$exit_status = 1; // EXIT_ERROR
		}
		
		log_message('error', 'Error: '.$view_path.' '.$status_code.' '.print_r($data,true));

		event::trigger('death.show');

		/* send it out */
		ci()->output
			->enable_profiler(false)
			->set_status_header($status_code)
			->set_content_type($mime_type, $charset)
			->set_output(o::view($view_path,$data))
			->_display();

		/* exit with the appropriate code */
		exit($exit_status);
	}

} /* end class */
