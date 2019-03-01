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
 * Validate class.
 *
 * Run Validation & Filters on passed variables
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2019
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version v2.0
 *
 * @uses # \Errors - Orange errors
 *
 * @config ~ preset filters
 *
 */
class Validate
{
	/**
	 * Storage for attached (Closure) validations
	 *
	 * @var array
	 */
	protected $attached = [];

	/**
	 * Storage the current validations error string usually in sprintf format
	 *
	 * @var string
	 */
	protected $error_string = '';

	/**
	 * Storage for the Human readable version of the field name can be used in the error string as sprintf parameter 1 ie. Last_name Last Name
	 *
	 * @var string
	 */
	protected $error_human = '';

	/**
	 * Storage for the Error options which can be used in the error string as sprintf parameter 2 ie. [1,34,67] becomes 1, 34, 67
	 *
	 * @var string
	 */
	protected $error_params = '';

	/**
	 * Storage for the field value which can be used in the error string as sprintf parameter 3
	 *
	 * @var string
	 */
	protected $error_field_value = '';

	/**
	 * Storage for the current field data being validated
	 *
	 * @var array
	 */
	protected $field_data = [];

	/**
	 * Local reference of validate configuration
	 *
	 * @var array
	 */
	protected $config = [];

	/**
	 * Local reference of Orange Error Object
	 *
	 * @var \Errors
	 */
	protected $errors;

	/**
	 *
	 * Constructor
	 *
	 * @access public
	 *
	 * @param array $config []
	 *
	 */
	public function __construct(array &$config=[])
	{
		$this->config = &$config;

		$this->errors = &ci('errors');

		log_message('info', 'Validate Class Initialized');
	}

	/**
	 *
	 * Set the current validation error group.
	 * if no group name is provided then the error classes uses the current group
	 * _Wrapper for error_
	 *
	 * @access public
	 * @uses \Errors
	 *
	 * @param string $index null
	 *
	 * @return Validate
	 *
	 * #### Example
	 * ```php
	 * ci('validate')->group('user_model');
	 * ```
	 */
	public function group(string $index = null) : Validate
	{
		$this->errors->group($index);

		return $this;
	}

	/**
	 *
	 * Get the current validation error group name.
	 * _Wrapper for error_
	 *
	 * @access public
	 * @uses \Errors
	 *
	 * @return string
	 *
	 * #### Example
	 * ```php
	 * $group_name = ci('validate')->get_group();
	 * ```
	 */
	public function get_group() : string
	{
		return $this->errors->get_group();
	}

	/**
	 *
	 * Clear the a error group
	 * if no group name is provided then the error classes uses the current group
	 * _Wrapper for error_
	 *
	 * @access public
	 * @uses \Errors
	 *
	 * @param string $index null
	 *
	 * @return Validate
	 *
	 * #### Example
	 * ```php
	 *
	 * ```
	 */
	public function clear(string $index = null) : Validate
	{
		$this->errors->clear($index);

		return $this;
	}

	/**
	 *
	 * Add a error to the Orange Error Object
	 *
	 * @access protected
	 * @uses \Errors
	 *
	 * @param string $fieldname null
	 *
	 * @return \Validate
	 *
	 */
	protected function add_error(string $fieldname = null) : Validate
	{
		/**
		 * sprintf argument 1 human name for field
		 * sprintf argument 2 human version of options (computer generated)
		 * sprintf argument 3 field value
		 */

		$this->errors->add(sprintf($this->error_string, $this->error_human, $this->error_params, $this->error_field_value), $fieldname);

		return $this;
	}

	/**
	 *
	 * Die if there are any errors
	 * if no group name is provided then the error classes uses the current group
	 * _Wrapper for error_
	 *
	 * @access public
	 * @uses \Errors
	 *
	 * @param $view 400
	 * @param string $index null
	 *
	 * @return Validate
	 *
	 */
	public function die_on_fail($view = '400', string $index = null) : Validate
	{
		/* if there is a error then you never return from this method call */
		$this->errors->die_on_error($view, $index);

		/* if there is no error */
		return $this;
	}

	/**
	 *
	 * Return if a error group has a error
	 * if no group name is provided then the error classes uses the current group
	 * _Wrapper for error_
	 *
	 * @access public
	 * @uses \Errors
	 *
	 * @param string $index null
	 *
	 * @return Bool
	 *
	 */
	public function success(string $index = null) : Bool
	{
		return (!$this->errors->has($index));
	}

	/**
	 *
	 * Attach a validation rule as a Closure
	 *
	 * @access public
	 *
	 * @param string $name
	 * @param closure $closure
	 *
	 * @return Validate
	 *
	 * #### Example
	 * ```php
	 * ci('validate')->attach('filter_lower',function(&$field, $options) { return strtolower($field); });
	 * ci('validate')->attach('return_true',function(&$field, $options) { return true; });
	 * ```
	 */
	public function attach(string $name, \closure $closure) : Validate
	{
		$this->attached[$this->_normalize_rule($name)] = $closure;

		return $this;
	}

	/**
	 *
	 * Run validation rules on a passed variable
	 * These are best used as filters
	 * but by following this with die_on_fail() you can use validations
	 *
	 * @access public
	 *
	 * @param string $rules
	 * @param &$field
	 * @param string $human null
	 *
	 * @return Validate
	 *
	 * #### Example
	 * ```php
	 *
	 * ```
	 */
	public function variable(string $rules = '', &$field, string $human = null) : Validate
	{
		return $this->single($rules, $field, $human);
	}

	/**
	 *
	 * Run validation rules on a input field
	 * These are best used as filters
	 * but by following this with die_on_fail() you can use validations
	 * this is more of a wrapper for ci('input')->request() to allow chaining
	 *
	 * @access public
	 * @uses \Input
	 *
	 * @param string $rules
	 * @param string $key
	 * @param $human null if true then return the fields validated value
	 *
	 * @return mixed
	 *
	 */
	public function request(string $rules, string $key, $human = null)
	{
		$field = ci('input')->request($key, null);

		$this->single($rules, $field, $human);

		ci('input')->set_request($key, $field);

		return ($human === true) ? $field : $this;
	}

	/**
	 *
	 * Run validations and based on if $fields contain multiple entries (array)
	 * determine if it's multi field or single field validation
	 *
	 * @access public
	 *
	 * @param $rules
	 * @param &$fields
	 * @param string $human used only on single [null]
	 *
	 * @return Validate
	 *
	 */
	public function run($rules = '', &$fields, string $human = null) : Validate
	{
		return (is_array($fields)) ? $this->multiple($rules, $fields) : $this->single($rules, $fields, $human);
	}

	/**
	 *
	 * Run Multiple validation rules over multiple fields
	 *
	 * @access public
	 *
	 * @param array $rules []
	 * @param array &$fields []
	 *
	 * @return Validate
	 *
	 */
	public function multiple(array $rules = [], array &$fields) : Validate
	{
		/* save this as a reference for the validations and filters to use */
		$this->field_data = &$fields;

		/* process each field and rule as a single rule, field, and human label */
		foreach ($rules as $fieldname=>$rule) {
			$this->single($rule['rules'], $this->field_data[$fieldname], $rule['label']);
		}

		/* break the reference */
		unset($this->field_data);

		/* now set it to empty */
		$this->field_data = [];

		return $this;
	}

	/**
	 *
	 * Run Validation rules on a single field value
	 *
	 * @access public
	 *
	 * @param $rules
	 * @param &$field
	 * @param string $human null
	 *
	 * @return Validate
	 *
	 */
	public function single($rules, &$field, string $human = null) : Validate
	{
		/* break apart the rules */
		if (!is_array($rules)) {
			/* is this a preset set in the configuration array? */
			$rules = (isset($this->config[$rules])) ? $this->config[$rules] : $rules;

			/* split these into individual rules */
			if (is_string($rules)) {
				$rules = explode('|', $rules);
			}
		}

		/* do we have any rules? */
		if (count($rules)) {
			/* field value before any validations / filters */
			$this->error_field_value = $field;

			/* yes - for each rule...*/
			foreach ($rules as $rule) {
				log_message('debug', 'Validate Rule '.$rule.' "'.$field.'" '.$human);

				/* no rule? exit processing of the $rules array */
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

				/* setup default of no parameters */
				$param = '';

				/* do we have parameters if so split them out */
				if (preg_match("/(.*?)\[(.*?)\]/", $rule, $match)) {
					$rule  = $match[1];
					$param = $match[2];
				}

				/* do we have a human readable field name? if not then try to make one */
				$this->error_human = ($human) ? $human : strtolower(str_replace('_', ' ', $rule));

				log_message('debug', 'Validate '.$rule.'['.$param.'] > '.$this->error_human);

				/* try to format the parameters into something human readable incase they need this in there error message  */
				if (strpos($param, ',') !== false) {
					$this->error_params = str_replace(',', ', ', $param);

					if (($pos = strrpos($this->error_params, ', ')) !== false) {
						$this->error_params = substr_replace($this->error_params, ' or ', $pos, 2);
					}
				} else {
					$this->error_params = $param;
				}

				/* hopefully error_params looks presentable now? */

				/* take action on a validation or filter - filters MUST always start with "filter_" */
				$success = (substr(strtolower($rule), 0, 7) == 'filter_') ? $this->_filter($field, $rule, $param) : $this->_validation($field, $rule, $param);

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

	/**
	 *
	 * Run a filter rule.
	 * Filters always start with the filter_ prefix
	 * Filters always return true (success)
	 * if you need to register a error use a validation
	 *
	 * @access protected
	 *
	 * @param &$field
	 * @param string $rule
	 * @param string $param null
	 *
	 * @return bool
	 *
	 */
	protected function _filter(&$field, string $rule, string $param = null) : bool
	{
		$class_name = $this->_normalize_rule($rule);
		$short_rule = substr($class_name, 7);

		if (isset($this->attached[$class_name])) {
			$this->attached[$class_name]($field, $param);
		} elseif (class_exists($class_name, true)) {
			(new $class_name($this->field_data))->filter($field, $param);
		} elseif (function_exists($short_rule)) {
			$field = ($param) ? $short_rule($field, $param) : $short_rule($field);
		} else {
			throw new \Exception('Could not filter '.$rule);
		}

		/* filters don't fail */
		return true;
	}

	/**
	 *
	 * Run a validation rule.
	 * returns true on success and false on error
	 *
	 * @access protected
	 *
	 * @param &$field
	 * @param string $rule
	 * @param string $param null
	 *
	 * @return bool
	 *
	 */
	protected function _validation(&$field, string $rule, string $param = null) : bool
	{
		$class_name = $this->_normalize_rule($rule);
		$short_rule = substr($class_name, 9);

		/* default error */
		$this->error_string = '%s is not valid.';

		if (isset($this->attached[$class_name])) {
			$success = $this->attached[$class_name]($field, $param, $this->error_string, $this->field_data, $this);
		} elseif (class_exists($class_name, true)) {
			$success = (new $class_name($this->field_data, $this->error_string))->validate($field, $param);
		} elseif (function_exists($short_rule)) {
			$success = ($param) ? $short_rule($field, $param) : $short_rule($field);
		} else {
			throw new \Exception('Could not validate '.$rule);
		}

		/* if success is really really false then it's a error */
		if ($success === false) {
			$this->add_error($this->error_human);
		} else {
			/* not a boolean then it's something useable */
			if (!is_bool($success)) {
				$field = $success;

				$success = true;
			}
		}

		return $success;
	}

	/**
	 *
	 * normalize the rule name.
	 * if they send in a rule without a prefix then it's a validate rule
	 * because they don't need a prefix
	 * filters must always include filter_
	 *
	 * @access protected
	 *
	 * @param string $name
	 *
	 * @return string
	 *
	 */
	protected function _normalize_rule(string $name) : string
	{
		/* normalize to lowercase */
		$name = strtolower($name);

		/* if validate or filter is already prepended */
		$prefix = (substr($name, 0, 9) != 'validate_' && (substr($name, 0, 7) != 'filter_')) ? 	'validate_' : '';

		return $prefix.$name;
	}
} /* end class */
