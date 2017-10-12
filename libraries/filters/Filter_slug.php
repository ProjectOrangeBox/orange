<?php
/**
 * Orange Framework validation rule
 *
 * This content is released under the MIT License (MIT)
 *
 * @package	CodeIgniter / Orange
 * @author	Don Myers
 * @license http://opensource.org/licenses/MIT MIT License
 * @link	https://github.com/ProjectOrangeBox
 *
 */
class Filter_slug extends Filter_base {

	public function filter(&$field, $options) {
	  // replace non letter or digits by -
	  $field = preg_replace('~[^\pL\d]+~u', '-', $field);
	
	  // transliterate
	  $field = iconv('utf-8', 'us-ascii//TRANSLIT', $field);
	
	  // remove unwanted characters
	  $field = preg_replace('~[^-\w]+~', '', $field);
	
	  // trim
	  $field = trim($field, '-');
	
	  // remove duplicate -
	  $field = preg_replace('~-+~', '-', $field);
	
	  // lowercase
	  $field = strtolower($field);
	}

} /* end class */