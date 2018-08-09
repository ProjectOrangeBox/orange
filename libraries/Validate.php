<?php
/**
 * Validate
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
 * core: input, output
 * libraries: errors, wallet
 * models:
 * helpers:
 * functions:
 *
 */
class Validate {

	/**
	 * track if the combined cached configuration has been loaded
	 *
	 * @var array
	 */
	protected $attached = [];

	/**
	 * track if the combined cached configuration has been loaded
	 *
	 * @var string
	 */
	protected $error_string = '';

	/**
	 * track if the combined cached configuration has been loaded
	 *
	 * @var array
	 */
	protected $field_data = [];

	protected $config;
	protected $input;
	protected $output;
	protected $errors;
	protected $wallet;

	/**
	 * __construct
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
	public function __construct(&$config) {
		$this->config = &$config;

		$this->input = &ci('input');
		$this->output = &ci('output');
		$this->errors = &ci('errors');
		$this->wallet = &ci('wallet');

		$this->clear();

		$attach = load_config('validate','attach');

		if (is_array($attach)) {
			foreach ($attach as $name=>$closure) {
				log_message('debug', 'Application "validate_'.$name.'" attached to Validate library.');

				$this->attached['validate_'.$name] = $closure;
			}
		}

		log_message('info', 'Validate Class Initialized');
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
		$this->errors->clear();

		return $this;
	}

	/**
	 * attach
	 * Insert description here
	 *
	 * @param $name
	 * @param closure
	 * @param $closure
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function attach($name, closure $closure) {
		$this->attached['validate_'.$name] = $closure;

		return $this;
	}

	/**
	 * die_on_fail
	 * Insert description here
	 *
	 * @param $view
	 *
	 * @return $this
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function die_on_fail($view = '400') {
		if ($this->errors->has()) {
			$this->errors->display($view, ['heading' => 'Validation Failed', 'message' => $this->errors->as_html()]);
		}

		return $this;
	}

	/**
	 * redirect_on_fail
	 * Insert description here
	 *
	 * @param $url
	 *
	 * @return $this
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function redirect_on_fail($url = null) {
		if ($this->errors->has()) {
			$url = (is_string($url)) ? $url : true;
			$this->wallet->msg($this->errors->as_html(), 'red', $url);
		}

		return $this;
	}

	/**
	 * json_on_fail
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
	public function json_on_fail() {
		if ($this->errors->has()) {
			$this->output->json(['ci_errors'=>$this->errors->as_data()])->_display();
			exit(1);
		}

		return $this;
	}

	/**
	 * success
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
	public function success() {
		return !$this->errors->has();
	}

	/**
	 * variable
	 * Insert description here
	 *
	 * @param $rules
	 * @param $field
	 * @param $human
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function variable($rules = '',&$field, $human = null) {
		return $this->single($rules, $field, $human);
	}

	/**
	 * request
	 * Insert description here
	 *
	 * @param $rules
	 * @param $key
	 * @param $human
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function request($rules = '', $key, $human = null) {
		$field = $this->input->request($key);
		$this->single($rules, $field, $human);
		$this->input->request_replace($key,$field);

		return ($human === true) ? $field : $this;
	}

	/**
	 * run
	 * Insert description here
	 *
	 * @param $rules
	 * @param $fields
	 * @param $human
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function run($rules = '', &$fields, $human = null) {
		return (is_array($fields)) ? $this->multiple($rules, $fields) : $this->single($rules, $fields, $human);
	}

	/**
	 * single
	 * Insert description here
	 *
	 * @param $rules
	 * @param $field
	 * @param $human
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function single($rules, &$field, $human = null) {
		if (!is_array($rules)) {
			$rules = (isset($this->config[$rules])) ? $this->config[$rules] : $rules;

			if (is_string($rules)) {
				$rules = explode('|', $rules);
			}
		}

		if (count($rules)) {
			foreach ($rules as $rule) {
				if (empty($rule)) {
					$success = true;
					break;
				}

				if ($rule == 'allow_empty') {
					if (empty($field)) {
						break;
					} else {
						continue;
					}
				}

				$param = null;

				if (preg_match("/(.*?)\[(.*?)\]/", $rule, $match)) {
					$rule  = $match[1];
					$param = $match[2];
				}

				$success            = false;
				$this->error_string = '%s is not valid.';
				$lowercase = strtolower($rule);
				$is_filter = (substr($lowercase,0,6) == 'filter');
				$class_name = ($is_filter) ? ucfirst($lowercase) : 'Validate_'.$lowercase;

				if ($plugin = $this->load_plugin($class_name,$is_filter)) {
					if ($is_filter) {
						$success = true;
						$plugin->filter($field, $param);
					} else {
						$success = $plugin->validate($field, $param);
					}
				} elseif (function_exists($rule)) {
					$success = ($param !== null) ? $rule($field,$param) : $rule($field);
				} elseif (isset($this->attached['validate_'.$rule])) {
					$success = $this->attached['validate_'.$rule]($field, $param, $this->error_string, $this->field_data, $this);
				} else {
					$this->error_string = 'Could not validate %s against '.$rule;
				}

				if (!$is_filter) {
					if ($success !== false) {
						if (!is_bool($success)) {
							$field = $success;
						}
					} else {
						$human = ($human) ? $human : strtolower(str_replace('_', ' ', $rule));
						if (strpos($param, ',') !== false) {
							$param = str_replace(',', ', ', $param);
							if (($pos = strrpos($param, ', ')) !== false) {
								$param = substr_replace($param, ' or ', $pos, 2);
							}
						}
						$this->errors->add(sprintf($this->error_string, $human, $param));
						break;
					}
				}
			}
		}

		return $this;
	}

	/**
	 * multiple
	 * Insert description here
	 *
	 * @param $rules
	 * @param $fields
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function multiple($rules, &$fields) {
		$this->field_data = &$fields;

		foreach ($rules as $fieldname => $rule) {
			$this->single($rule['rules'], $this->field_data[$fieldname], $rule['label']);
		}

		$fields = &$this->field_data;

		return $this;
	}

	/**
	 * load_plugin
	 * Insert description here
	 *
	 * @param $class_name
	 * @param $is_filter
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	protected function load_plugin($class_name,$is_filter) {
		$plugin = false;

		if (class_exists($class_name,true)) {
			$plugin = ($is_filter) ? new $class_name($this->field_data) : new $class_name($this->field_data, $this->error_string);
		}

		/* if it's not there then it might be a built in function */

		return $plugin;
	}

} /* end class */
