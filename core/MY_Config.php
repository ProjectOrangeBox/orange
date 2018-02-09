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
 * core: load
 * libraries:
 * models:
 * helpers:
 * functions:
 *
 */

class MY_Config extends CI_Config {
	protected $setup = false;

	/**
	 * Fetch a config file item
	 *
	 * @param	string	$item	Config item name
	 * @param	string	$index	Index name
	 * @return	string|null	The configuration item or NULL if the item doesn't exist
	 *
	 * Overridden
	 *
	 */
	public function item($item,$index=null) {
		log_message('debug', 'MY_Config::item::'.$item);

		$value = null;

		if (!$this->setup) {
			if (class_exists('CI_Controller',false)) {
				if (class_exists('Cache_export',false)) {
					$this->setup = true;

					$this->_load_combined_config();
				}
			}
		}

		if (strpos($item,'.') !== false) {
			/* dot notation style? */
			list($file,$key) = explode('.', strtolower($item), 2);

			if (!$key) {
				$value = isset($this->config[$file]) ? $this->config[$file] : $index;
			} else {
				$value = isset($this->config[$file], $this->config[$file][$key]) ? $this->config[$file][$key] : $index;
			}

		} else {
			/* default style */
			$value = parent::item($item,$index);
		}

		return $value;
	}

	public function flush() {
		log_message('debug', 'MY_Config::settings_flush');

		return ci('cache')->export->delete('config');
	}

	protected function _load_combined_config() {
		$built_config = ci('cache')->export->get('config');

		if (!is_array($built_config)) {
			$built_config = [];

			$paths = orange_paths();

			foreach ($paths['configs']['root'] as $group_key=>$filepath) {
				$config = null;

				include $filepath;

				if (is_array($config)) {
					foreach ($config as $key => $value) {
						$built_config[$group_key][strtolower($key)] = $value;
					}
				}
			}

			if (is_array($paths['configs'][ENVIRONMENT])) {
				foreach ($paths['configs'][ENVIRONMENT] as $group_key=>$filepath) {
					$config = null;

					include $filepath;

					if (is_array($config)) {
						foreach ($config as $key => $value) {
							$built_config[$group_key][strtolower($key)] = $value;
						}
					}
				}
			}

			/* database configs */
			if (parent::item('no_database_settings') !== true) {
				$db_array = ci('o_setting_model')->pull();

				if (is_array($db_array)) {
					foreach ($db_array as $record) {
						$built_config[strtolower($record->group)][strtolower($record->name)] = convert_to_real($record->value);
					}
				}
			}

			$built_config = $this->config + $built_config;

			ci('cache')->export->save('config',$built_config);
		}

		$this->config = $built_config;
	}

} /* end file */
