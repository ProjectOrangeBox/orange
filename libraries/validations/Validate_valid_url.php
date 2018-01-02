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

class Validate_valid_url extends Validate_base {
	public function validate(&$field, $options) {
		$this->error_string = '%s must contain a valid URL.';

		if (empty($field)) {
			return false;
		} elseif (preg_match('/^(?:([^:]*)\:)?\/\/(.+)$/', $field, $matches)) {
			if (empty($matches[2])) {
				return false;
			} elseif (!in_array($matches[1], ['http', 'https'], true)) {
				return false;
			}
			$field = $matches[2];
		}

		$field = 'http://'.$field;

		if (version_compare(PHP_VERSION, '5.2.13', '==') or version_compare(PHP_VERSION, '5.3.2', '==')) {
			sscanf($field, 'http://%[^/]', $host);
			$field = substr_replace($field, strtr($host, ['_' => '-', '-' => '_']), 7, strlen($host));
		}

		return (filter_var($field, FILTER_VALIDATE_URL) !== FALSE);
	}
} /* end file */