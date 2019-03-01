<?php
/**
 * Validate_is_unique
 * Insert description here
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2018
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version v2.0
 *
 * required
 * core:
 * libraries:
 * models:
 * helpers:
 * functions:
 *
 * @help is not unique to the table and field name in the parameter.
 * @help Note: This rule requires Query Builder to be enabled in order to work.
 *
 */
class Validate_is_unique extends \Validate_base
{
	public function validate(&$field, string $options = '') : bool
	{
		$this->error_string = '%s must contain a unique value.';

		list($tablename, $columnname) = explode('.', $options, 2);

		if (empty($tablename)) {
			return false;
		}

		if (empty($columnname)) {
			return false;
		}

		return isset(ci()->db) ? (ci()->db->limit(1)->get_where($tablename, [$columnname => $field])->num_rows() === 0) : false;
	}
}
