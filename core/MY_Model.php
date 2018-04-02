<?php
/**
 * MY_Model
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
 * libraries: errors, validate
 * models:
 * helpers:
 * functions:
 *
 */
class MY_Model extends CI_Model {
	/**
	 * array of ALL of the rules for this model
	 *
	 * example:
	 *  'id' => ['field' => 'id', 'label' => 'Id', 'rules' => 'required|integer|max_length[10]|less_than[4294967295]|filter_int[10]'],
	 *
	 * @var array
	 */
	protected $rules = [];

	/**
	 * set of rules to use
	 *
	 * example:
	 *  'basic_form'=>'id,first_name,last_name'
	 *  'adv_form'=>'id,first_name,last_name,age,weight'
	 *
	 * @var array
	 */
	protected $rule_sets = [];

	/**
	 * name of the object
	 *
	 * @var string
	 */
	protected $object = null;

	/**
	 * object
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
	public function object() {
		return $this->object;
	}

	/**
	 * rules
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
	public function rules() {
		return $this->rules;
	}

	/**
	 * rule
	 * Insert description here
	 *
	 * @param $name
	 * @param $second
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function rule($name, $second = null) {
		return ($second) ? $this->rules[$name][$second] : $this->rules[$name];
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
		ci('errors')->clear();

		return $this;
	}

	/**
	 * validate
	 * Insert description here
	 * 'id' => ['field' => 'id', 'label' => 'Id', 'rules' => 'required|integer|max_length[10]|less_than[4294967295]|filter_int[10]'],
	 *
	 * @param $data - array of key value pairs to test
	 * @param $rules -
	 *
	 * @return boolean - has error
	 *
	 */
	public function validate(&$data, $rules = true) {
		/* if it's already a array then it's already in the format we need */
		if (!is_array($rules)) {
			/* if rules is true then just use the data array keys as the fields to validate to */
			if ($rules === true) {
				$rules_names = array_keys($data);
			} elseif (is_string($rules)) {
				/* if it's a string then see if it's a rule set if not treat as a comma sep list of field to validate */
				$rules_names = explode(',',(isset($this->rule_sets[$rules]) ? $this->rule_sets[$rules] : $rules));
			}

			/* copy all the rules */
			$rules = $this->rules;

			/* now filter out the rules we don't need */
			$this->only_columns($rules, $rules_names);
		}

		/* let's make sure the data "keys" have rules */
		$this->only_columns($data, $rules);

		/* did we actually get any rules? */
		if (count($rules)) {
			/* run the rules on the data array */
			ci('validate')->multiple($rules, $data);
		}

		/* return if we got any errors */
		return !ci('errors')->has();
	}

	/**
	 * remove_columns
	 * Insert description here
	 *
	 * @param $data array
	 * @param $columns string or array
	 *
	 * @return object
	 *
	 */
	public function remove_columns(&$data, $columns = []) {
		/* convert string with commas to array */
		$columns = (!is_array($columns)) ? explode(',', $columns) : $columns;

		/* remove any data "key" in columns array */
		$data = array_diff_key($data,array_combine($columns,$columns));

		return $this;
	}

	/**
	 * only_columns
	 * Insert description here
	 *
	 * @param $data array
	 * @param $columns string or array
	 *
	 * @return object
	 */
	public function only_columns(&$data, $columns = []) {
		/* convert string with commas to array */
		$columns = (!is_array($columns)) ? explode(',', $columns) : $columns;

		/* let' make sure the values are singular not an array if they are singular then create the key/value pair */
		if (!is_array(current($columns))) {
			$columns = array_combine($columns,$columns);
		}

		/* remove any data "key" not in columns array */
		$data = array_intersect_key($data,$columns);

		return $this;
	}

	/*
	remap the "fake" column name to real column names
	this is when a rule key is password_not_empty but the field is password for example
	*/
	public function remap_columns(&$data, $rules = []) {
		if (!$this->skip_rules && count($rules)) {
			$new_data = [];
			foreach ($rules as $key=>$rule) {
				if (isset($data[$key])) {
					$new_data[$rule['field']] = $data[$key];
				}
			}
			$data = $new_data;
		}

		return $this;
	}

} /* end class */
