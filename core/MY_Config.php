<?php
/**
 * Orange Framework Extension
 *
 * @package	CodeIgniter / Orange
 * @author Don Myers
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 *
 * required
 * core:
 * libraries: o::convert_to_real, cache_var_export
 * models o_setting_model
 * helpers:
 *
 */
class MY_Config extends CI_Config {
	protected $setup = false; /* has the combined configuration been loaded? */

	/**
	 * item function.
	 *
	 * add additional functionality to CodeIgniters config->item function
	 * this will allow dot notation to retrieve config values as well as env variables and database "settings"
	 *
	 * @author Don Myers
	 * @access public
	 * @param mixed $item
	 * @param mixed $index (default: null)
	 * @return void
	 */
	public function item($item,$index=null) {
		/* setup the default */
		$value = null;

		/* did we try to setup the config "super array" */
		if (!$this->setup) {
			/* is the controller loaded yet */
			if (class_exists('CI_Controller',false)) {
				/* is the cache_var_export attached yet? because _load_combined_config needs it to cache */
				if (class_exists('Cache_var_export',false)) {
					/* this keep it from looping while it's working */
					$this->setup = true;

					/* load the combined config */
					$this->_load_combined_config();
				}
			}
		}

		/* are they trying to load a dot notation config variable - orange style */
		if (strpos($item,'.') !== false) {
			list($file,$key) = explode('.', strtolower($item), 2);

			if (!$key) {
				$value = isset($this->config[$file]) ? $this->config[$file] : $index;
			} else {
				$value = isset($this->config[$file], $this->config[$file][$key]) ? $this->config[$file][$key] : $index;
			}
		} else {
			/* default back to CodeIgniter style - parent */
			$value = parent::item($item,$index);
		}

		return $value;
	}

	/**
	 * flush function.
	 *
	 * if we remove this the system will create a new one as needed
	 *
	 * @author Don Myers
	 * @access public
	 * @return void
	 */
	public function flush() {
		log_message('debug', 'MY_Config::settings_flush');

		return cache_var_export::delete('config');
	}

	/**
	 * _load_combined_config function.
	 *
	 * load all the settings if they aren't already loaded and return the entire array
	 *
	 * @author Don Myers
	 * @access protected
	 * @return void
	 */
	protected function _load_combined_config() {
		log_message('debug', 'MY_Config::_load_combined_config');

		/* if the cache is on get the cached version */
		$built_config = cache_var_export::get('config');

		/* did we get back a complete settings array */
		if (!is_array($built_config)) {
			/* no -- setup the empty array */
			$built_config = [];

			# +-+-+-+-+-+-+-+-+-+-+-+-+-+
			# |e|n|v|i|r|o|n|m|e|n|t|a|l|
			# +-+-+-+-+-+-+-+-+-+-+-+-+-+

			if (!file_exists(ROOTPATH . '/_env')) {
				die('_env file missing');
			}

			/* bring in the system .env files */
			$e = require ROOTPATH . '/_env';

			foreach ($e as $key => $value) {
				$built_config['env'][strtolower($key)] = $value;
			}

			# +-+-+-+-+ +-+-+-+-+-+-+
			# |f|i|l|e| |c|o|n|f|i|g|
			# +-+-+-+-+ +-+-+-+-+-+-+
			$config_files = glob(APPPATH . 'config/*.php');

			foreach ($config_files as $file) {
				$config = null;

				include $file;

				/* is $config now a array? It should be because all config files use $config['name'] = 'value'; */
				if (is_array($config)) {
					/* what's the name of this group? */
					$group_key = strtolower(basename($file, '.php'));

					/* put everything into the settings array */
					foreach ($config as $key => $value) {
						$built_config[$group_key][strtolower($key)] = $value;
					}
				}
			}

			# +-+-+-+-+-+-+-+-+-+-+-+-+-+ +-+-+-+-+ +-+-+-+-+-+-+
			# |e|n|v|i|r|o|n|m|e|n|t|a|l| |f|i|l|e| |c|o|n|f|i|g|
			# +-+-+-+-+-+-+-+-+-+-+-+-+-+ +-+-+-+-+ +-+-+-+-+-+-+
			if (ENVIRONMENT) {
				$config_files = glob(APPPATH . 'config/' . ENVIRONMENT . '/*.php');

				foreach ($config_files as $file) {
					$config = null;

					include $file;

					if (is_array($config)) {
						/* what's the name of this group? */
						$group_key = strtolower(basename($file, '.php'));

						/* put everything into the settings array */
						foreach ($config as $key => $value) {
							$built_config[$group_key][strtolower($key)] = $value;
						}
					}
				}
			}

			# +-+-+-+-+-+-+-+-+ +-+-+-+-+-+-+-+-+
			# |d|a|t|a|b|a|s|e| |s|e|t|t|i|n|g|s|
			# +-+-+-+-+-+-+-+-+ +-+-+-+-+-+-+-+-+

			/* are they using the database settings? */
			if (parent::item('no_database_settings') !== true) {
				/* let's make sure the model is loaded */
				ci()->load->model('o_setting_model');

				$db_array = ci()->o_setting_model->get_many_by(['enabled' => 1]);

				if (is_array($db_array)) {
					foreach ($db_array as $record) {
						/* let's make sure a boolean is a boolean and a integer is a integer etc... */
						$built_config[strtolower($record->group)][strtolower($record->name)] = o::convert_to_real($record->value);
					}
				}
			}

			/* ok let's cache our array for faster access next time */

			/* combined them with the already loaded settings */
			$built_config = $this->config + $built_config;

			/* save them in the cache */
			cache_var_export::save('config',$built_config);
		}

		/* now config's array contains these */
		$this->config = $built_config;
	}

} /* end class */
