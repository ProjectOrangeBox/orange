<?php
/**
 * Orange
 *
 * An open source extensions for CodeIgniter 3.x
 *
 * This content is released under the MIT License (MIT)
 * Copyright (c) 2014 - 2019, Project Orange Box
 */

/**
 * Extension to CodeIgniter Model Class
 * Provides validation in the model
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2019
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version v2.0
 * @filesource
 *
 */

class MY_Model extends \CI_Model
{
	/**
	 * Formatted array of rules for this model
	 * ['id' => ['field' => 'id', 'label' => 'Id', 'rules' => 'required|integer|max_length[10]|less_than[4294967295]|filter_int[10]']]
	 *
	 * @var Array
	 */
	protected $rules = [];

	/**
	 * Used to tell the model to skip all rule validations
	 *
	 * @var Boolean
	 */
	protected $skip_rules = false;

	/**
	 * Named rules sets to use on Model method called
	 *
	 * [
	 *		'basic_form'=>'id,first_name,last_name',
	 *		'adv_form'=>'id,first_name,last_name,age,weight',
	 *		'insert'=>'first_name,last_name,age,weight',
	 *		'update'=>'id,first_name,last_name,age,weight',
	 * ]
	 *
	 * @var Array
	 */
	protected $rule_sets = [];

	/**
	 * Name of the object
	 *
	 * @var String
	 */
	protected $object = null;

	/**
	 * Get the object name
	 *
	 * @access public
	 *
	 * @return String
	 *
	 */
	public function object() : String
	{
		return $this->object;
	}

	/**
	 * Get the models Rules
	 *
	 * @access public
	 *
	 * @return Array
	 *
	 */
	public function rules() : array
	{
		return $this->rules;
	}

	/**
	 * Get a rule by column name or column name and section
	 *
	 * @param $key column name
	 * @param $section column section
	 *
	 * @return mixed
	 */
	public function rule(string $key, $section = null)
	{
		log_message('debug', 'MY_Model::rule '.$key.' '.$section);

		$rule = ($section) ? $this->rules[$key][$section] : $this->rules[$key];

		return ($rule === null) ? false : $rule;
	}

	/**
	 *
	 * Clear any validation errors for this object
	 *
	 * @access public
	 *
	 * @return \MY_Model
	 *
	 */
	public function clear() : MY_Model
	{
		log_message('debug', 'MY_Model::clear '.$this->object);

		/* validation wrapper */
		ci('validate')->clear($this->object);

		return $this;
	}

	/**
	 *
	 * Preform Model Validation
	 * If rules is boolean true
	 *   then we auto use the rule names which match the data array keys
	 * If rules is an array
	 *   then we use them verbatim
	 * If rules is a string
	 *   then we convert it to a array by separating the string on commas
	 *
	 * @access public
	 *
	 * @param Array &$data key value pairs to test
	 * @param Mixed $rules rules to use for the validations
	 *
	 * @return Bool Success
	 *
	 */
	public function validate(array &$data, $rules = true) : Bool
	{
		log_message('debug', 'MY_Model::validate');

		/**
		 * if it's already a array then it's already in the format we need
		 */
		if (!is_array($rules)) {
			/**
			 * if rules is true then just use the data array keys as the fields to validate to
			 */
			if ($rules === true) {
				$rules_names = array_keys($data);
			} elseif (is_string($rules)) {
				/**
				 * if it's a string then see if it's a rule set if not treat as a comma sep list of field to validate
				 */
				$rules_names = explode(',', (isset($this->rule_sets[$rules]) ? $this->rule_sets[$rules] : $rules));
			}

			/**
			 * copy all the rules so we can modify the copy
			 */
			$rules = $this->rules;

			/**
			 * now filter out the rules we don't need
			 */
			$this->only_columns($rules, $rules_names);
		}

		/**
		 * let's make sure the data "keys" have rules
		 */
		$this->only_columns($data, $rules);

		/**
		 * Save the current group in validate
		 * so we can put it back after this model is done validating this model
		 */
		$previous_error_group = ci('validate')->get_group();

		/**
		 * did we actually get any rules?
		 */
		if (count($rules)) {
			/**
			 * run the rules on the data array
			 */
			ci('validate')->group($this->object)->multiple($rules, $data);
		}

		/**
		 * return if we got any errors
		 */
		$success = ci('validate')->success($this->object);

		/**
		 * we are done put back the previous error group
		 */
		ci('validate')->group($previous_error_group);

		return $success;
	}

	/**
	 *
	 * remove matching keys in the data array from input in columns
	 * remove the matching keys in the data array from input in columns
	 * columns can be a array ['firstname','lastname','age'] or comma sep string 'firstname,lastname,age'
	 *
	 * @access public
	 *
	 * @param Array &$data
	 * @param $columns []
	 *
	 * @return MY_Model
	 *
	 */
	public function remove_columns(array &$data, $columns = []) : MY_Model
	{
		log_message('debug', 'MY_Model::remove_columns');

		/**
		 * convert string with commas to array
		 */
		$columns = (!is_array($columns)) ? explode(',', $columns) : $columns;

		/**
		 * remove any data "key" in columns array
		 */
		$data = array_diff_key($data, array_combine($columns, $columns));

		return $this;
	}

	/**
	 *
	 * only the matching keys in the data array from input in columns
	 * columns can be a array ['firstname','lastname','age'] or comma sep string 'firstname,lastname,age'
	 *
	 * @access public
	 *
	 * @param Array &$data
	 * @param $columns []
	 *
	 * @return MY_Model
	 *
	 */
	public function only_columns(array &$data, $columns = []) : MY_Model
	{
		log_message('debug', 'MY_Model::only_columns');

		/**
		 * convert string with commas to array
		 */
		$columns = (!is_array($columns)) ? explode(',', $columns) : $columns;

		/**
		 * let' make sure the values are singular not an array if they are singular then create the key/value pair
		 */
		if (!is_array(current($columns))) {
			$columns = array_combine($columns, $columns);
		}

		/**
		 * remove any data "key" not in columns array
		 */
		$data = array_intersect_key($data, $columns);

		return $this;
	}

	/**
	 *
	 * Remap columns in the input array (data) with the rules field names
	 *
	 * @access public
	 *
	 * @param Array &$data
	 * @param Array $rules []
	 *
	 * @return MY_Model
	 *
	 * #### Example
	 * ```php
	 * $rules = [
	 *	'id' => ['field' => 'id', 'label' => 'Id', 'rules' => 'required|integer],
	 *	'key' => ['field' => 'key', 'label' => 'Key', 'rules' => 'required|strtolower'],
	 *	'description' => ['field' => 'description', 'label' => 'Description', 'rules' => 'required'],
	 *	'group' => ['field' => 'group', 'label' => 'Group', 'rules' => 'required'],
	 *	'group_test' => ['field' => 'group'],
	 * ];
	 *
	 * $data = ['id'=>123,'key'=>'cookies','description'=>'The greatest show ever','group_test'=>'live'];
	 *
	 * $this->my_model->remap_columns($data,$rules);
	 *
	 * $data now contains ['id'=>123,'key'=>'cookies','description'=>'The greatest show ever','group'=>'live']
	 *
	 * ```
	 */
	public function remap_columns(array &$data, array $rules = []) : MY_Model
	{
		log_message('debug', 'MY_Model::remap_columns');

		if (!$this->skip_rules && count($rules)) {
			$remapped_data = [];

			foreach ($rules as $key=>$rule) {
				if (isset($data[$key])) {
					$remapped_data[$rule['field']] = $data[$key];
				}
			}

			/**
			 * reassign the new data to the data reference
			 */
			$data = $remapped_data;
		}

		return $this;
	}
} /* end class */
