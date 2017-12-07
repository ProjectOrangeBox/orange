<?php
/**
 *
 * This base model should only contain functions (or stubs of functions)
 * that all models that extend it might have (basic CRUD for example)
 * it also contains the model validation functions
 * since all models should validate data
 *
 * Orange Framework Extension
 *
 * @package	CodeIgniter / Orange
 * @author Don Myers
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 *
 * required
 * core: load
 * libraries: validate, error
 * models:
 * helpers:
 * functions:
 *
 */
class MY_Model extends CI_Model {
	protected $rules     = [];
	protected $rule_sets = [];

	/* for a database model this would be considered the "table" */
	protected $object = null;

	/**
	 * object function.
	 *
	 * return the object name (read only)
	 * 
	 * @author Don Myers
	 * @access public
	 * @return void
	 */
	public function object() {
		return $this->object;
	}

	/**
	 * rules function.
	 *
	 * return all rules (read only)
	 * 
	 * @author Don Myers
	 * @access public
	 * @return void
	 */
	public function rules() {
		return $this->rules;
	}

	/**
	 * rule function.
	 *
	 * return a specific rule and optional a second level inside that rule (read only)
	 * 
	 * @author Don Myers
	 * @access public
	 * @param mixed $name
	 * @param mixed $second (default: null)
	 * @return void
	 */
	public function rule($name, $second = null) {
		return ($second) ? $this->rules[$name][$second] : $this->rules[$name];
	}

	/**
	 * add_rules function.
	 *
	 * return a array of model rules adding yours in front of the models
	 * 
	 * $this->validate->multiple($this->o_setting_model->add_rules($form_rules['form1']), $this->input->request())->ci_errors_on_fail();
	 * 
	 * @author Don Myers
	 * @access public
	 * @param array $rules
	 * @return array
	 */
	public function add_rules($rules=[]) {
		$merged = [];
	
		foreach ($rules as $key=>$rule) {
			$merged[$key] = $this->rule($key);
			
			$merged[$key]['rules'] = trim($rule.'|'.$merged[$key]['rules'],'|');
		}
		
		return $merged;
	}

	/**
	 * clear function.
	 *
	 * clear all errors - wrapper 
	 * 
	 * @author Don Myers
	 * @access public
	 * @return void
	 */
	public function clear() {
		errors::clear();

		return $this;
	}

	/**
	 * validate function.
	 * Validate model based on
	 * the data passed in (key value pair) and
	 * the rule_name (from model) or rule set passed in (validation rules)
	 * 
	 * @author Don Myers
	 * @access public
	 * @param mixed &$data
	 * @param bool $rules (default: true)
	 * @return void
	 */
	public function validate(&$data, $rules = true) {
		/* load the validate library if it's not already loaded */
		$this->load->library('validate');

		/* rules needs to be a array of rule sets to use for validation */
		if ($rules === true) {
			/* dynamically build the rules from the associated keys in $data */
			$rules = $this->string2array(array_keys($data));
		} elseif (is_string($rules)) {
			/* build a rule set based off of the items in my list */
			$rules = $this->string2array(explode(',', $this->rule_sets[$rules]));
		}

		/* remove all fields not part of the rule set */
		$this->only_columns_with_rules($data, $rules);

		/* run the model rules */
		if (count($rules)) {
			ci()->validate->multiple($rules, $data);
		}

		return !errors::has();
	}

	/**
	 * remove_columns function.
	 *
	 * Remove these array keys from the data
	 * 
	 * @author Don Myers
	 * @access protected
	 * @param mixed &$data
	 * @param mixed $columns
	 * @return void
	 */
	protected function remove_columns(&$data, $columns) {
		$columns = (!is_array($columns)) ? explode(',', $columns) : $columns;
		
		foreach ($columns as $attr) {
			if (is_object($row)) {
				unset($row->$attr);
			} else {
				unset($row[$attr]);
			}
		}

		return $this;
	}

	/**
	 * only_columns function.
	 *
	 * only these array keys
	 * 
	 * @author Don Myers
	 * @access protected
	 * @param mixed &$data
	 * @param mixed $columns (default: [])
	 * @return void
	 */
	protected function only_columns(&$data, $columns = []) {
		$columns = (!is_array($columns)) ? explode(',', $columns) : $columns;

		foreach ($data as $key => $value) {
			if (!in_array($key, $columns)) {
				unset($data[$key]);
			}
		}

		return $this;
	}

	/**
	 * only_columns_with_rules function.
	 * 
	 * @author Don Myers
	 * @access protected
	 * @param mixed &$data
	 * @param mixed $rules (default: null)
	 * @return void
	 */
	protected function only_columns_with_rules(&$data, $rules = null) {
		$rule_fields = [];

		$rules = ($rules) ? $rules : $this->rules;

		foreach ($rules as $key => $rule) {
			/* do we have a rule for this field? */
			if (isset($rule['field'])) {
				$rule_fields[] = $rule['field'];
			}
		}

		$this->only_columns($data, $rule_fields);

		return $this;
	}

	/**
	 * string2array function.
	 * 
	 * @author Don Myers
	 * @access protected
	 * @param mixed $fields
	 * @return void
	 */
	protected function string2array($fields) {
		/* prep return value */
		$new_array = [];

		foreach ($fields as $field) {
			/* let's make sure it's not empty (ie. extra comma or something */
			if (isset($this->rules[$field])) {
				$new_array[$field] = $this->rules[$field];
			}
		}

		return $new_array;
	}
	
} /* end MY_Model */