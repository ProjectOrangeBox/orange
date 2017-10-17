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
	 * add function.
	 *
	 * @access public
	 * @static
	 * @param mixed $msg
	 * @return void
	 */
	public static function add($msg) {
		$current_errors = ci()->load->get_var(self::$errors_variable);

		$current_errors[$msg] = $msg;

		ci()->load->vars(self::$errors_variable, $current_errors);
	}

	/**
	 * clear function.
	 *
	 * @access public
	 * @static
	 * @return void
	 */
	public static function clear() {
		ci()->load->vars(self::$errors_variable, []);
	}

	/**
	 * has function.
	 *
	 * @access public
	 * @static
	 * @return void
	 */
	public static function has() {
		return (count(ci()->load->get_var(self::$errors_variable)) != 0);
	}

	/**
	 * as_array function.
	 *
	 * @access public
	 * @static
	 * @return void
	 */
	public static function as_array() {
		return ci()->load->get_var(self::$errors_variable);
	}

	/**
	 * as_html function.
	 *
	 * @access public
	 * @static
	 * @param mixed $prefix (default: null)
	 * @param mixed $suffix (default: null)
	 * @return void
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
	 * as_cli function.
	 *
	 * @access public
	 * @static
	 * @return void
	 */
	public static function as_cli() {
		return errors::as_html(chr(9), chr(10));
	}

	/**
	 * as_data function.
	 *
	 * @access public
	 * @static
	 * @return void
	 */
	public static function as_data() {
		$errors = ci()->load->get_var(self::$errors_variable);

		return ['records' => array_values($errors)] + ['count' => count($errors)];
	}

	/**
	 * as_json function.
	 *
	 * @access public
	 * @static
	 * @return void
	 */
	public static function as_json() {
		return json_encode(self::as_data(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT);
	}

	public static function show($txt) {
		self::display('general',['heading'=>'General Error','message'=>$txt]);
	}

	/**
	 * display function.
	 *
	 * @access public
	 * @static
	 * @param mixed $view
	 * @param mixed $data (default: [])
	 * @param int $status_code (default: 500)
	 * @param mixed $extra (default: [])
	 * @return void
	 */
	public static function display($view, $data = [], $status_code = 500, $extra = []) {
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
		$charset     = ($extra['charset']) ? $extra['charset'] : $charset;
		$mime_type   = ($extra['mime_type']) ? $extra['mime_type'] : $mime_type;
		$view_folder = ($extra['view_folder']) ? $extra['view_folder'] : $view_folder;

		/* what is the view path? */
		$view_path = 'errors/' . $view_folder . '/error_' . str_replace('.php', '', $view) . '.php';

		/* clean up the status code and setup the exit status code (taken from the CodeIgniter error handler) */
		$status_code = abs($status_code);

		if ($status_code < 100) {
			$exit_status = $status_code + 9; // 9 is EXIT__AUTO_MIN
			$status_code = 500;
		} else {
			$exit_status = 1; // EXIT_ERROR
		}
		
		log_message('error', 'Error: '.$view_path.' '.$status_code.' '.print_r($data,true));

		$view_file = stream_resolve_include_path('views/' . $view_path);

		/* if we are in development mode create the file in the application folder */
		if ($view_file === false) {
			if (DEBUG == 'development') {
				/* then create it */
				@mkdir(ROOTPATH . '/application/views/' . dirname($view_path), 0777, true);

				file_put_contents(ROOTPATH . '/application/views/' . $view_path, '<?php' . PHP_EOL . PHP_EOL . ' echo "Error View File: ".__FILE__;' . PHP_EOL);

				die('Error View File ../views/' . $view_path . ' Not Found - because you are in development mode it has been automatically created for you.');
			} else {
				show_error('could not locate view');
			}
		}

		$output = self::view($view_file,$data);

		event::trigger('death.show');

		/* send it out */
		ci()->output
			->enable_profiler(false)
			->set_status_header($status_code)
			->set_content_type($mime_type, $charset)
			->set_output($output)
			->_display();

		/* exit with the appropriate code */
		exit($exit_status);
	}
	
	static protected function view($_view,$_data) {
		extract($_data, EXTR_PREFIX_INVALID, '_');

		/* start output cache */
		ob_start();

		/* load in view (which now has access to the in scope view data */
		include $_view;

		/* capture cache and return */
		return ob_get_clean();
	}

} /* end class */
