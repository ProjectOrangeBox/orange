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

	/* used to tell the model to skip all rule validations */
	protected $skip_rules = false;

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
	 *
	 * @return object
	 *
	 */
	public function object() {
		return $this->object;
	}

	/**
	 * rules
	 *
	 * @return models rules
	 *
	 */
	public function rules() {
		return $this->rules;
	}

	/**
	 * rule
	 * get a rule by column name or column name and section
	 *
	 * @param $column
	 * @param $section
	 *
	 * @return array
	 */
	public function rule($key, $section = null) {
		return ($section) ? $this->rules[$key][$section] : $this->rules[$key];
	}

	/**
	 * clear
	 * wrapper to clear errors object
	 *
	 *
	 * @return $this
	 *
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
		return !(ci('errors')->has());
	}

	/**
	 * remove_columns
	 *
	 * remove matching keys in the data array from input in columns
	 * remove the matching keys in the data array from input in columns
	 * columns can be a array ['firstname','lastname','age'] or comma sep string 'firstname,lastname,age'
	 *
	 * @param $data array
	 * @param $columns string or array
	 *
	 * @return $this
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
	 *
	 * only the matching keys in the data array from input in columns
	 * columns can be a array ['firstname','lastname','age'] or comma sep string 'firstname,lastname,age'
	 *
	 * @param $data array
	 * @param $columns string or array
	 *
	 * @return $this
	 *
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

	/**
	 * remap_columns
	 *
	 * 'long_description' => ['field' => 'description', 'label' => 'Description', 'rules' => 'max_length[255]|filter_input[255]|is_uniquem[o_role_model.description.id]'],
	 *
	 * This remaps "long_description" into a new array where it's now "description"
	 *
	 * @param $data array passed by reference
	 * @param $rules array
	 *
	 * @return $this
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
