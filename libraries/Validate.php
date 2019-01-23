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
 * core:
 * libraries: errors
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
	protected $error_human = '';
	protected $error_params = '';

	/**
	 * track if the combined cached configuration has been loaded
	 *
	 * @var array
	 */
	protected $field_data = [];

	protected $config;
	protected $errors;

	protected $loaded = [];

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
	public function __construct(&$config=[]) {
		$this->config = &$config;

		$this->errors = &ci('errors');

		$attach = load_config('validate','attach');

		if (is_array($attach)) {
			foreach ($attach as $name=>$closure) {
				log_message('debug', 'Application "validate_'.$name.'" attached to validate library.');

				$this->attached['validate_'.$name] = $closure;
			}
		}

		log_message('info', 'Validate Class Initialized');
	}

	public function group($index = null)
	{
		$this->errors->group($index);

		return $this;
	}

	public function get_group()
	{
		return $this->errors->get_group();
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
	public function clear($index=null)
	{
		$this->errors->clear($index);

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
		$this->attached[$this->_normalize_rule($name)] = $closure;

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
	public function die_on_fail($view = '400',$index=null) {
		$this->errors->die_on_error($view,$index);

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
	public function success($index = null) {
		return (!$this->errors->has($index));
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
		$field = ci('input')->request($key);

		$this->single($rules, $field, $human);

		ci('input')->set_request($key,$field);

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
		/* break apart the rules */
		if (!is_array($rules)) {
			$rules = (isset($this->config[$rules])) ? $this->config[$rules] : $rules;

			if (is_string($rules)) {
				$rules = explode('|', $rules);
			}
		}

		/* do we have any rules? */
		if (count($rules)) {
			/* yes */
			foreach ($rules as $rule) {
				log_message('debug', 'Validate Rule '.$rule.' "'.$field.'" '.$human);

				/* no rules exit processing of the $rules array */
				if (empty($rule)) {
					log_message('debug', 'Validate no validation rule.');

					$success = true;
					break;
				}

				/* do we have this special rule? */
				if ($rule == 'allow_empty') {
					log_message('debug', 'Validate allow_empy skipping the rest if empty.');

					if (empty($field)) {
						/* end processing of the $rules array */
						break;
					} else {
						/* skip the rest of the current foreach but don't stop processing the $rules array  */
						continue;
					}
				}

				/* do we have parameters */
				$param = null;

				/* split them out */
				if (preg_match("/(.*?)\[(.*?)\]/", $rule, $match)) {
					$rule  = $match[1];
					$param = $match[2];
				}

				$this->error_human = ($human) ? $human : strtolower(str_replace('_', ' ', $rule));

				log_message('debug', 'Validate '.$rule.'['.$param.'] > '.$this->error_human);

				if (strpos($param, ',') !== false) {
					$this->error_params = str_replace(',', ', ', $param);

					if (($pos = strrpos($this->error_params, ', ')) !== false) {
						$this->error_params = substr_replace($this->error_params, ' or ', $pos, 2);
					}
				} else {
					$this->error_params = $param;
				}

				/* take action on a validation or filter - filters MUST always start with "filter_" */
				$success = (substr($rule,0,7) == 'filter_') ? $this->_filter($field,$rule,$param) : $this->_validation($field,$rule,$param);

				log_message('debug', 'Validate Success '.$success);

				/* bail on first failure */
				if ($success === false) {
					/* end processing of the $rules array */
					return $this;
				}
			}
		}

		return $this;
	}

	protected function _filter(&$field,$rule,$param) {
		$class_name = $this->_normalize_rule($rule);
		$short_rule = substr($class_name,7);

		if (isset($this->attached[$class_name])) {
			$this->attached[$class_name]($field, $param);
		} elseif ($this->loaded[$class_name]) {
			$this->loaded[$class_name]->filter($field, $param);
		} elseif (class_exists($class_name,true)) {
			$this->loaded[$class_name] = new $class_name($field);
			$this->loaded[$class_name]->filter($field, $param);
		} elseif (function_exists($short_rule)) {
			$field = ($param) ? $short_rule($field,$param) : $short_rule($field);
		} else {
			throw new Exception('Could not filter '.$rule);
		}

		/* filters don't fail */
		return true;
	}

	protected function _validation(&$field,$rule,$param) {
		$class_name = $this->_normalize_rule($rule);
		$short_rule = substr($class_name,9);

		/* default error */
		$this->error_string = '%s is not valid.';

		if (isset($this->attached[$class_name])) {
			$success = $this->attached[$class_name]($field, $param, $this->error_string, $this->field_data, $this);
		} elseif ($this->loaded[$class_name]) {
			$success = $this->loaded[$class_name]->validate($field, $param);
		} elseif (class_exists($class_name,true)) {
			$this->loaded[$class_name] = new $class_name($this->field_data, $this->error_string);
			$success = $this->loaded[$class_name]->validate($field, $param);
		} elseif (function_exists($short_rule)) {
			$success = ($param) ? $short_rule($field,$param) : $short_rule($field);
		} else {
			throw new Exception('Could not validate '.$rule);
		}

		if ($success !== false) {
			if (!is_bool($success)) {
				$field = $success;
				$success = true;
			}
		} else {
			$this->add_error(null,$this->error_human);
		}

		return $success;
	}

	protected function add_error($index=null,$fieldname=null) {
		$this->errors->group($index)->add(sprintf($this->error_string, $this->error_human, $this->error_params),$fieldname);
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

	protected function _normalize_rule($name) {
		/* normalize to lowercase */
		$name = strtolower($name);

		/* if validate or filter is already prepended */
		$prefix = (substr($name,0,9) != 'validate_' && (substr($name,0,7) != 'filter_')) ? 	'validate_' : '';

		return $prefix.$name;
	}

} /* end class */
