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

class Validate {
	protected $config;
	protected $attached = [];
	protected $error_string;
	protected $field_data = [];

	public function __construct() {

		$this->config = config('validate');

		require_once __DIR__.'/Validate_base.php';
		require_once __DIR__.'/Filter_base.php';

		$this->clear();

		if (file_exists(APPPATH.'/config/validate.php')) {
			$attach = [];

			include APPPATH.'/config/validate.php';

			foreach ($attach as $name=>$closure) {
				log_message('debug', 'Application "validate_'.$name.'" attached to Validate library.');
				$this->attached['validate_'.$name] = $closure;
			}
		}

		if (file_exists(APPPATH.'/config/'.ENVIRONMENT.'/validate.php')) {
			$attach = [];

			include APPPATH.'/config/'.ENVIRONMENT.'/validate.php';

			foreach ($attach as $name=>$closure) {
				log_message('debug', ENVIRONMENT.' "validate_'.$name.'" attached to Validate library.');
				$this->attached['validate_'.$name] = $closure;
			}
		}

		log_message('info', 'Validate Class Initialized');
	}

	public function clear() {
		errors::clear();

		return $this;
	}

	public function attach($name, closure $closure) {
		log_message('debug', '"validate_'.$name.'" attached to Validate library.');

		$this->attached['validate_'.$name] = $closure;

		return $this;
	}

	public function die_on_fail($view = '400') {
		if (errors::has()) {
			log_message('debug', 'validate error die_on_fail '.errors::as_cli());
			errors::display($view, ['heading' => 'Validation Failed', 'message' => errors::as_html()]);
		}

		return $this;
	}

	public function redirect_on_fail($url = null) {
		if (errors::has()) {
			log_message('debug', 'validate error redirect_on_fail '.errors::as_cli());
			$url = (is_string($url)) ? $url : true;
			ci()->wallet->msg(errors::as_html(), 'red', $url);
		}

		return $this;
	}

	public function json_on_fail() {
		if (errors::has()) {
			ci()->output->json(['ci_errors'=>errors::as_data()])->_display();
			exit(1);
		}

		return $this;
	}

	public function success() {
		return !errors::has();
	}

	public function variable($rules = '',&$field, $human = null) {
		return $this->single($rules, $field, $human);
	}

	public function request($rules = '', $key, $human = null) {
		$field = ci()->input->request($key);

		$this->single($rules, $field, $human);

		ci()->input->request_replace($key,$field);

		return ($human === true) ? $field : $this;
	}

	public function run($rules = '', &$fields, $human = null) {
		return (is_array($fields)) ? $this->multiple($rules, $fields) : $this->single($rules, $fields, $human);
	}

	public function single($rules, &$field, $human = null) {
		$rules = (isset($this->config[$rules])) ? $this->config[$rules] : $rules;

		log_message('debug', 'validate::single Human Label: "'.$human.'" Rule: "'.$rules.'" Field: "'.$field.'"');

		if (!empty($rules)) {
			$rules = explode('|', $rules);

			foreach ($rules as $rule) {
				if (empty($rule)) {
					$success = true;

					break;
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

						errors::add(sprintf($this->error_string, $human, $param));

						break;
					}
				}
			}
		}

		return $this;
	}

	public function multiple($rules, &$fields) {
		$this->field_data = &$fields;

		foreach ($rules as $fieldname => $rule) {
			$this->single($rule['rules'], $this->field_data[$fieldname], $rule['label']);
		}

		$fields = &$this->field_data;

		return $this;
	}

	protected function load_plugin($class_name,$is_filter) {
		$plugin = false;

		if (class_exists($class_name,true)) {
			$plugin = ($is_filter) ? new $class_name($this->field_data) : new $class_name($this->field_data, $this->error_string);
		}

		return $plugin;
	}

} /* end file */