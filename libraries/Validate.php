<?php
/**
 * Orange Framework Extension
 *
 * @package	CodeIgniter / Orange
 * @author Don Myers
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 *
 * New Validation Library
 * a little more generic than form_validation
 * but, it uses the same functions for validation
 *
 * required
 * core: 
 * libraries: 
 * models: 
 * helpers:
 * functions: 
 *
 * 1. form_data
 *
 */
class Validate {
	protected $config; /* local copy of config */
	protected $attached = []; /* storage for validations attached as closures */
	protected $error_string; /* the current test error message */
	protected $field_data = []; /* local copy of the fields to validate */

	/**
	 * Construct
	 *
	 * @param		array		configuration sent in via the library load call
	 * @depends loader because we use the settings method to load in the filesystem/database settings
	 */
	public function __construct() {
		/*
		Grab a local copy of the CodeIgniter Configs 
		
		This also attaches 
		*/
		$this->config = config('validate');

		/* load the required plugin parent library */
		require_once __DIR__ . '/Validate_base.php';
		require_once __DIR__ . '/Filter_base.php';

		/* setup */
		$this->clear();
		
		/* attach any validations in the config file */
		if (file_exists(APPPATH.'/config/validate.php')) {
			$attach = [];
			
			include APPPATH.'/config/validate.php';

			foreach ($attach as $name=>$closure) {
				log_message('debug', 'Application "validate_' . $name . '" attached to Validate library.');

				$this->attached['validate_' . $name] = $closure;
			}			
		}

		/* attach any validations in the environmental config file */
		if (file_exists(APPPATH.'/config/'.ENVIRONMENT.'/validate.php')) {
			$attach = [];
			
			include APPPATH.'/config/'.ENVIRONMENT.'/validate.php';

			foreach ($attach as $name=>$closure) {
				log_message('debug', ENVIRONMENT.' "validate_' . $name . '" attached to Validate library.');

				$this->attached['validate_' . $name] = $closure;
			}			
		}

		log_message('info', 'Validate Class Initialized');
	}

	/**
	 * clear
	 *
	 * Clear / Init the library for processing.
	 * if you need to process more than set of input you
	 * will need to call this method to clear the library between calls
	 *
	 * @return this	to allow chaining
	 */
	/**
	 * clear function.
	 * 
	 * @access public
	 * @return void
	 */
	public function clear() {
		errors::clear();

		return $this;
	}

	/**
	 * attach
	 * attach a validation function (closure)
	 *
	 * When the closures is called it will be passed:
	 * Argument 1 a reference to all field data
	 * Argument 2 a refenence to the variable that needs processing
	 * Argument 3 any extra parameters in brackets of the rule [1,2,3] in string format '1,2,3' .
	 *            these will need to be seperated with list() + explode() for example or through
	 * Argument 4 a reference to the error messesage
	 * Argument 5 a reference to this class (validate)
	 * 
	 */
	public function attach($name, closure $closure) {
		log_message('debug', '"validate_' . $name . '" attached to Validate library.');

		$this->attached['validate_' . $name] = $closure;

		return $this;
	}

	/**
	 * die_on_fail
	 *
	 * wether to die automatically on the first validation failure.
	 * This could be useful when testing input from a user which should be valid already
	 * but may have been changed by the user for example.
	 *
	 * @param	boolean Turn on or off this feature. Default true.
	 * @return this	to allow chaining
	 */
	public function die_on_fail($view = '400') {
		if (errors::has()) {
			log_message('debug', 'validate error die_on_fail ' . errors::as_cli());

			errors::display($view, ['heading' => 'Validation Failed', 'message' => errors::as_html()]);
		}

		return $this;
	}

	/**
	 * redirect_on_fail function.
	 * 
	 * @access public
	 * @param mixed $url (default: null)
	 * @return void
	 */
	public function redirect_on_fail($url = null) {
		if (errors::has()) {
			log_message('debug', 'validate error redirect_on_fail ' . errors::as_cli());

			$url = (is_string($url)) ? $url : true;

			ci()->wallet->msg(errors::as_html(), 'red', $url);
		}

		return $this;
	}
	
	/*
	 * $this->validate->multiple('required|strtolower|valid_email', $this->input->request())->json_on_fail();
	 */
	public function json_on_fail() {
		if (errors::has()) {
			/* show errors and die */
			ci()->output->json(['ci_errors'=>errors::as_data()])->_display();

			exit(1);
		}
	
		return $this;
	}

	/**
	 * success function.
	 * 
	 * @access public
	 * @return void
	 */
	public function success() {
		return !errors::has();
	}

	/**
	 * variable function.
	 * 
	 * @access public
	 * @param mixed $rules
	 * @param mixed &$field
	 * @param mixed $human or return value (default: '')
	 * @return void
	 */
	public function variable($rules = '',&$field, $human = null) {
		/* field modified by reference */
		return $this->single($rules, $field, $human);
	}

	public function request($rules = '', $key, $human = null) {
		/* copy the input into a variable which we can pass by reference */
		$field = ci()->input->request($key);
		
		/* pass in rules and field by reference */
		$this->single($rules, $field, $human);
		
		/* now let's put it back incase they are filtering or something else */
		ci()->input->request_replace($key,$field);
		
		return ($human === true) ? $field : $this;
	}

	/**
	 * run function.
	 * 
	 * @access public
	 * @param mixed $rules
	 * @param mixed &$fields
	 * @param mixed $human (default: null)
	 * @return void
	 */
	public function run($rules = '', &$fields, $human = null) {
		return (is_array($fields)) ? $this->multiple($rules, $fields) : $this->single($rules, $fields, $human);
	}

	/**
	 * one
	 *
	 * validate a single field with a set of rules.
	 *
	 * @param	string	rules in CodeIgniter form validation format
	 * @param	mixed		variable to be tested passed by reference so it can be modified by the method if needed
	 * @param	string	human label used in the error message as a sprintf parameter
	 * @return boolean true on success false on failure
	 */
	public function single($rules, &$field, $human = null) {
		/* is the rule set ($rules) stored in the validate config? */
		$rules = (isset($this->config[$rules])) ? $this->config[$rules] : $rules;

		log_message('debug', 'validate::single Human Label: "' . $human . '" Rule: "' . $rules . '" Field: "' . $field . '"');

		/* do we even have a rules to validate against? */
		if (!empty($rules)) {
			$rules = explode('|', $rules);

			foreach ($rules as $rule) {
				/* do we even have a rules to validate against? */
				if (empty($rule)) {
					$success = true;

					/* break out of the foreach loop */
					break;
				}

				/*
				Strip the parameter (if exists) from the rule
				Rules can contain a parameter: max_length[5]
				*/
				$param = null;

				if (preg_match("/(.*?)\[(.*?)\]/", $rule, $match)) {
					$rule  = $match[1];
					$param = $match[2];
				}

				$success            = false; /* default to fail */
				$this->error_string = '%s is not valid.'; /* default error */
				
				$lowercase = strtolower($rule);
				
				/* if it starts with the prefix 'filter' then it's a filter */
				$is_filter = (substr($lowercase,0,6) == 'filter');
				
				/* fix up the PHP class name */
				$class_name = ($is_filter) ? ucfirst($lowercase) : 'Validate_' . $lowercase;

				/* now we need to find this bugger */

				/* Orange Package Location or some where else */
				if ($plugin = $this->load_plugin($class_name,$is_filter)) {
					/* $class_name contains the name of the class just loaded */
					if ($is_filter) {
						/* filters don't fail */
						$success = true;
						
						/* run the filter */
						$plugin->filter($field, $param);
					} else {
						/* validation can fail */
						$success = $plugin->validate($field, $param);
					}
				} elseif (function_exists($rule)) {
					/* is it a PHP method? */
					$success = ($param !== null) ? $rule($field,$param) : $rule($field);
				} elseif (isset($this->attached['validate_' . $rule])) {
					/* field data, current field, current field param, validation object */
					$success = $this->attached['validate_' . $rule]($field, $param, $this->error_string, $this->field_data, $this);
				} else {
					/* rule not found */
					$this->error_string = 'Could not validate %s against ' . $rule;
				}
				
				/* filters don't fail so we don't need to run this logic */
				if (!$is_filter) {
					if ($success !== false) {
						if (!is_bool($success)) {
							/* not false */
							$field = $success;
						}
					} else {
						/* if the label is not provided try to gussy up the the rule name for human consumption */
						$human = ($human) ? $human : strtolower(str_replace('_', ' ', $rule));
	
						/* gussy up , separated values in the parameters */
						if (strpos($param, ',') !== false) {
							/* add spaces after the commas to make it look more human */
							$param = str_replace(',', ', ', $param);
	
							/* Replace last , with or */
							if (($pos = strrpos($param, ', ')) !== false) {
								$param = substr_replace($param, ' or ', $pos, 2);
							}
						}
	
						errors::add(sprintf($this->error_string, $human, $param));
	
						/* first error leave now */
						break;
					}
				} /* endif is filter */
			} /* end foreach */
		} /* end !empty(rule) */

		return $this;
	}

	/**
	 * multiple
	 *
	 * validate multiple fields with a set of fields
	 *
	 * @param	array array of rules in CodeIgniter Format
	 * @param	array mixed variables to be tested passed by reference so it can be modified by the method if needed
	 * @return this
	 */
	public function multiple($rules, &$fields) {
		/* make a reference to it so we have a local version */
		$this->field_data = &$fields;

		foreach ($rules as $fieldname => $rule) {
			/* success fail doesn't matter until we run all the tests on all of the fields */
			$this->single($rule['rules'], $this->field_data[$fieldname], $rule['label']);
		}

		$fields = &$this->field_data;

		return $this;
	}

	/**
	 * Find a filter or validation plugin
	 * load it and create a instance
	 * validation plugins are in the libraries/validations
	 * filter plugins are in the libraries/filters
	 * 
	 * @access protected
	 * @param mixed $class_name
	 * @param boolean $is_filter
	 * @return instance of 	the loaded plugin or false if it wasn't loaded
	 */
	protected function load_plugin($class_name,$is_filter) {
		$plugin = false;
		
		/* all variables passed by reference */
		if (class_exists($class_name,true)) {
			$plugin = ($is_filter) ? new $class_name($this->field_data) : new $class_name($this->field_data, $this->error_string);
		}

		return $plugin;
	}

} /* end class */