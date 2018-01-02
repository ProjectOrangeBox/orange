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

class MY_Config extends CI_Config {
	protected $setup = false;

	public function item($item,$index=null) {
		$value = null;

		if (!$this->setup) {

			if (class_exists('CI_Controller',false)) {

				if (class_exists('Cache_var_export',false)) {

					$this->setup = true;

					$this->_load_combined_config();
				}
			}
		}

		if (strpos($item,'.') !== false) {
			list($file,$key) = explode('.', strtolower($item), 2);
			if (!$key) {
				$value = isset($this->config[$file]) ? $this->config[$file] : $index;
			} else {
				$value = isset($this->config[$file], $this->config[$file][$key]) ? $this->config[$file][$key] : $index;
			}
		} else {

			$value = parent::item($item,$index);
		}
		return $value;
	}

	public function flush() {
		log_message('debug', 'MY_Config::settings_flush');
		return cache_var_export::delete('config');
	}

	protected function _load_combined_config() {
		log_message('debug', 'MY_Config::_load_combined_config');

		$built_config = cache_var_export::get('config');

		if (!is_array($built_config)) {

			$built_config = [];

			foreach ($_ENV as $key => $value) {
				$built_config['env'][strtolower($key)] = $value;
			}
												$config_files = glob(APPPATH.'config/*.php');
			foreach ($config_files as $file) {
				$config = null;
				include $file;

				if (is_array($config)) {

					$group_key = strtolower(basename($file, '.php'));

					foreach ($config as $key => $value) {
						$built_config[$group_key][strtolower($key)] = $value;
					}
				}
			}
												if (ENVIRONMENT) {
				$config_files = glob(APPPATH.'config/'.ENVIRONMENT.'/*.php');
				foreach ($config_files as $file) {
					$config = null;
					include $file;
					if (is_array($config)) {

						$group_key = strtolower(basename($file, '.php'));

						foreach ($config as $key => $value) {
							$built_config[$group_key][strtolower($key)] = $value;
						}
					}
				}
			}


			if (parent::item('no_database_settings') !== true) {

				ci()->load->model('o_setting_model');
				$db_array = ci()->o_setting_model->pull();
				if (is_array($db_array)) {
					foreach ($db_array as $record) {

						$built_config[strtolower($record->group)][strtolower($record->name)] = convert_to_real($record->value);
					}
				}
			}


			$built_config = $this->config + $built_config;

			cache_var_export::save('config',$built_config);
		}

		$this->config = $built_config;
	}
} /* end file */