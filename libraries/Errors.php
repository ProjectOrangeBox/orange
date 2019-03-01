<?php
/**
 * Orange
 *
 * An open source extensions for CodeIgniter 3.x
 *
 * This content is released under the MIT License (MIT)
 * Copyright (c) 2014 - 2019, Project Orange Box
 */

namespace projectorangebox\orange\libraries;

/**
 * Unified Error collecting class.
 *
 * Collection of errors with multiple grouping as well as displaying of errors.
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2019
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version v2.0
 * @filesource
 *
 * @uses input \Input
 * @uses output \Output
 * @uses event \Event
 *
 * @config html_prefix `<p class="{group class} orange-errors">`
 * @config html_suffix `</p>`
 * @config html_group_class `{group class}`
 * @config default error group `records`
 * @config auto detect `true`
 * @config errors view path `'/application/views/errors/'`
 *
 */
class Errors
{
	/**
	 * errors configuration array
	 *
	 * @var array
	 */
	protected $config;

	/**
	 * CodeIgniter Input object
	 *
	 * @var Input
	 */
	protected $input;

	/**
	 * CodeIgniter Output object
	 *
	 * @var Output
	 */
	protected $output;

	/**
	 * Orange Event object
	 *
	 * @var Event
	 */
	protected $event;

	/**
	 * html output error prefix
	 *
	 * @var string
	 */
	protected $html_prefix;

	/**
	 * html output error suffix
	 *
	 * @var string
	 */
	protected $html_suffix;

	/**
	 * html output class replacement tag ie. {class}
	 *
	 * @var string
	 */
	protected $html_group_class;

	/**
	 * array of errors
	 *
	 * @var array []
	 */
	protected $errors = [];

	/**
	 * current error array group
	 *
	 * @var string
	 */
	protected $current_group;

	/**
	 * default error array group
	 * when no group is specified this group is the one an error is added to.
	 * this then makes it the default or starting group.
	 *
	 * @var string
	 */
	protected $default_group;

	/**
	 * array of errors to prevent duplicates
	 *
	 * @var array []
	 */
	protected $duplicates = [];

	/**
	 * PHP request type cli, ajax|json, html, array
	 *
	 * @var string
	 */
	protected $request_type = 'array';

	/**
	 * Path to all error view files
	 *
	 * @var string
	 */
	protected $errors_view_path = '';

	/**
	 * Constructor
	 *
	 * @access public
	 *
	 * @param array $config []
	 *
	 */
	public function __construct(array &$config = [])
	{
		$this->config = &$config;

		$this->input = &ci('input');
		$this->output = &ci('output');
		$this->event = &ci('event');

		$this->html_prefix = $this->config['html_prefix'] ?? '<p class="{group class} orange-errors">';
		$this->html_suffix = $this->config['html_suffix'] ?? '</p>';
		$this->html_group_class = $this->config['html_group_class'] ?? '{group class}';

		$this->default_group = $this->config['default error group'] ?? 'records';
		$this->current_group = $this->default_group;

		$this->errors_view_path = $this->config['errors view path'] ?? ROOTPATH.'/application/views/errors/';

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
	 * @access public
	 *
	 * @return string
	 *
	 */
	public function __toString() : string
	{
		log_message('debug', 'Errors::__toString');

		return $this->get();
	}

	/**
	 *
	 * Get the default error group.
	 *
	 * @access public
	 *
	 * @return string
	 *
	 */
	public function get_default_group() : string
	{
		return $this->default_group;
	}

	/**
	 *
	 * Get the current error group.
	 *
	 * @access public
	 *
	 * @return string
	 *
	 */
	public function get_group() : string
	{
		return $this->current_group;
	}

	/**
	 *
	 * Set the error group for proceeding calls
	 *
	 * @access public
	 *
	 * @param string $group
	 *
	 * @return Errors
	 *
	 */
	public function group(string $group) : Errors
	{
		$this->current_group = $group;

		log_message('debug', 'Errors::group::'.$this->current_group);

		return $this;
	}

	/**
	 *
	 * Returns an array of all groups
	 *
	 * @access public
	 *
	 * @return array
	 *
	 * #### Example
	 * ```php
	 * $array = ci('errors')->groups();
	 * ```
	 */
	public function groups() : array
	{
		return array_keys($this->errors);
	}

	/**
	 *
	 * Set the request type for this classes dynamic methods
	 *
	 * @access public
	 *
	 * @param string $request_type cli|ajax|json|html|array
	 *
	 * @throws Exception
	 * @return Errors
	 *
	 */
	public function set_request_type(string $request_type) : Errors
	{
		log_message('debug', 'Errors::as::'.$request_type);

		/* options include cli, ajax, html */
		if (!in_array($request_type, ['cli','ajax','json','html','array'])) {
			throw new \Exception(__METHOD__.' unknown type '.$request_type.'.');
		}

		$this->request_type = $request_type;

		return $this;
	}

	/**
	 *
	 * Get the current errors
	 *
	 * @access public
	 *
	 * @return mixed
	 *
	 * #### Example
	 * ```php
	 * $foo->set_request_type('html')->get();
	 * ```
	 *
	 */
	public function get()
	{
		log_message('debug', 'Errors::get');

		switch ($this->request_type) {
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
	 *
	 * Add an error to the current group with optional field-name.
	 *
	 * @access public
	 *
	 * @param string $msg
	 * @param string $fieldname null
	 *
	 * @return Errors
	 *
	 * #### Example
	 * ```php
	 * $foo->group('foobar')->add('Error!')->set_request_type('cli')->get();
	 * ```
	 *
	 */
	public function add(string $msg, string $fieldname = null) : Errors
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
	 *
	 * Clear the specified group or current group
	 *
	 * @access public
	 *
	 * @param $group null
	 *
	 * @return Errors
	 *
	 * #### Example
	 * ```php
	 * $foo->clear('groupa');
	 * ```
	 *
	 */
	public function clear(string $group=null) : Errors
	{
		$group = ($group) ? $group : $this->current_group;

		log_message('debug', 'Errors::clear::'.$group);

		$this->errors[$group] = [];

		/* chain-able */
		return $this;
	}

	/**
	 *
	 * Returns wiether the specified group or current group has any errors (true)
	 *
	 * @access public
	 *
	 * @param string $group null
	 *
	 * @return boolean
	 *
	 * #### Example
	 * ```php
	 * $has_errors = $foo->has('groupa');
	 * ```
	 *
	 */
	public function has(string $group=null) : bool
	{
		$group = ($group) ? $group : $this->current_group;

		$has = (bool)count($this->errors[$group]);

		log_message('debug', 'Errors::has::'.$group.' '.$has);

		/* do we have any errors? */
		return $has;
	}

	/**
	 *
	 * returns if any groups have an error
	 *
	 * @access public
	 *
	 * @return bool
	 *
	 */
	public function has_any() : bool
	{
		foreach ($this->errors as $group=>$errors) {
			if (count($this->errors[$group])) {
				return true;
			}
		}

		return false;
	}

	/**
	 *
	 * Information on all errors
	 *
	 * @access public
	 *
	 * @param
	 *
	 * @return array
	 *
	 * #### Example
	 * ```php
	 * $array = ci('errors')->error_info();
	 * ```
	 */
	public function error_info() : array
	{
		return $this->errors;
	}

	/**
	 *
	 * Returns errors as array
	 *
	 * @access public
	 *
	 * @param string $group null
	 *
	 * @return array
	 *
	 * #### Example
	 * ```php
	 * $array = ci('errors')->as_array();
	 * $array = ci('errors')->as_array('groupa');
	 * $array = ci('errors')->as_array('groupa,groupc');
	 * $array = ci('errors')->as_array(['groupa','groupc']);
	 * ```
	 *
	 */
	public function as_array(string $group=null) : array
	{
		log_message('debug', 'Errors::as_array::'.$group);

		$array = $this->errors;

		if ($group) {
			if (is_array($group)) {
				$groups = $group;
			} else {
				$groups = explode(',', $group);
			}

			if (count($groups) > 1) {
				/* multi leveled */
				$multiple = [];

				foreach ($groups as $m) {
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
	 *
	 * Returns errors as HTML.
	 *
	 * @access public
	 *
	 * @param string $prefix null
	 * @param string $suffix null
	 * @param string $group null
	 *
	 * @return string
	 *
	 */
	public function as_html(string $prefix = null, string $suffix = null, string $group = null) : string
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
							$html .= str_replace($this->html_group_class, 'error-group-'.$grouping, $prefix.trim($val).$suffix);
						}
					}
				} else {
					if (!empty(trim($errors))) {
						$html .= str_replace($this->html_group_class, 'error-group-'.$grouping, $prefix.trim($errors).$suffix);
					}
				}
			}
		}

		return $html;
	}

	/**
	 *
	 * Returns errors as formatted JSON string.
	 *
	 * @access public
	 *
	 * @param string $group null
	 *
	 * @return string
	 *
	 */
	public function as_cli(string $group = null) : string
	{
		log_message('debug', 'Errors::as_cli::'.$group);

		/* return as string with tabs and line-feeds */
		return json_encode($this->as_array($group), JSON_PRETTY_PRINT).PHP_EOL;
	}

	/**
	 *
	 * Returns errors as JSON string.
	 *
	 * @access public
	 *
	 * @param string $group null
	 *
	 * @return string
	 *
	 */
	public function as_json(string $group = null) : string
	{
		log_message('debug', 'Errors::as_json::'.$group);

		return json_encode($this->as_array($group), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT);
	}

	/**
	 *
	 * Show error view on error and die.
	 *
	 * @access public
	 *
	 * @param $view 400
	 * @param string $group null
	 *
	 * @return Errors
	 *
	 *
	 * #### Example
	 * ```php
	 * ci('errors')->group('foo')->add('Oh No!')->die_on_error(400,'foo');
	 * ```
	 *
	 */
	public function die_on_error($view = 400, string $group = null) : Errors
	{
		$group = ($group) ? $group : $this->current_group;

		log_message('debug', 'Errors::die_on_error::'.$view.' '.$group);

		if ($this->has($group)) {
			$this->display($view);
		}

		return $this;
	}

	/**
	 *
	 * Generic Error Message.
	 *
	 * @access public
	 *
	 * @param string $message
	 * @param int $status_code 500
	 * @param string $heading An Error Was Encountered
	 *
	 * @return void
	 *
	 * #### Example
	 * ```php
	 * ci('errors')->show('Uh Oh!');
	 * ```
	 *
	 */
	public function show(string $message, int $status_code = 500, string $heading = 'An Error Was Encountered') : void
	{
		/* show the errors */
		$this->display('general', ['heading'=>$heading,'message'=>$message], $status_code);
	}

	/**
	 *
	 * Display error(s) view and exit
	 *
	 * @access public
	 *
	 * @param string $view
	 * @param array $data []
	 * @param int $status_code 500
	 * @param array $override []
	 *
	 * @return void
	 *
	 * #### Example
	 * ```php
	 * ci('errors')->display(...);
	 * ```
	 *
	 */
	public function display(string $view, array $data = [], int $status_code = 500, array $override = []) : void
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

		$data['heading'] = $data['heading'] ?? 'Fatal Error '.$status_code;
		$data['message'] = $data['message'] ?? 'Unknown Error';

		switch ($output_format) {
			case 'cli':
				$this->set_request_type('cli');
				$view_folder = 'cli';
			break;
			case 'json':
			case 'ajax':
				$this->set_request_type('ajax');
				$view_folder = 'ajax';
				$mime_type   = 'application/json';
			break;
			default:
				$this->set_request_type('html');
				$view_folder = 'html';
				$mime_type = 'text/html';
				$charset = 'utf-8';
		}

		$view_folder = ($override['view_folder']) ? $override['view_folder'] : $view_folder;
		$view_path = $view_folder.'/error_'.str_replace('.php', '', $view);

		/* get "as" using __toString */
		$data['message'] = (string)$this;

		$charset     = $override['charset'] ?? $charset;
		$mime_type   = $override['mime_type'] ?? $mime_type;

		$status_code = abs($status_code);

		log_message('debug', 'Errors::display '.$status_code.' '.$mime_type.' '.$charset.' '.$view_path);

		if ($status_code < 100) {
			$exit_status = $status_code + 9;
			$status_code = 500;
		} else {
			$exit_status = 1;
		}

		log_message('error', 'Error: '.$view_path.' '.$status_code.' '.json_encode($data));

		$this->event->trigger('errors.display', $view_path, $data, $mime_type, $charset, $exit_status);

		$this->output
			->enable_profiler(false)
			->set_status_header($status_code)
			->set_content_type($mime_type, $charset)
			->set_output($this->error_view($view_path, $data))
			->_display();

		$this->output->_exit($exit_status);
	}

	/**
	 *
	 * Actual low level error view load and rendering method.
	 *
	 * @access protected
	 *
	 * @param string $_view
	 * @param array $_data []
	 *
	 * @return string
	 *
	 */
	protected function error_view(string $_view, array $_data=[]) : string
	{
		log_message('debug', 'Errors::error_view::'.$_view);

		/* get a list of all the found views */
		if (!$_file = realpath($this->errors_view_path.$_view.'.php')) {
			throw new \Exception('Could not locate error view "'.$this->errors_view_path.$_view.'"');
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
