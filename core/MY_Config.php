<?php
/**
 * MY_Config
 * The Config class provides a means to retrieve configuration preferences.
 * These preferences can come from the default config file (application/config/config.php)
 * or from your own custom config files.
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2018
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version 2.0
 *
 * required
 * core: cache
 * libraries:
 * models: o_setting_model
 * helpers:
 * functions:
 *
 */
class MY_Config extends CI_Config {
	/**
	 * track if the combined cached configuration has been loaded
	 *
	 * @var boolean
	 */
	protected $setup = false;

	/**
	 * provide dot notation selection
	 * this is the new "recommended" way to make sure you get database values as well
	 *
	 * @param string $setting dot notation format config item
	 * @param mixed $default if not found
	 *
	 * @return string|null The configuration item or NULL if the item doesn't exist
	 *
	 * @access public
	 * @uses none
	 * @examples dot_item('email.mailtype','html')
	 */
	public function dot_item($setting,$default=null) {
		log_message('debug', 'MY_Config::item_dot::'.$setting);

		$this->_load_config();

		$key = false;

		if (strpos($setting,'.')) {
			list($file,$key) = explode('.', strtolower($setting), 2);
		} else {
			$file = strtolower($setting);
		}

		if ($key) {
			$value = isset($this->config[$file], $this->config[$file][$key]) ? $this->config[$file][$key] : $default;
		} else {
			$value = isset($this->config[$file]) ? $this->config[$file] : $default;
		}

		return $value;
	}

	/**
	 * Change dot notation config value
	 * NOT Saved between requests
	 *
	 * @param string $setting Config item name in dot notation format
	 * @param mixed $value value to set configuration value to
	 *
	 * @return this
	 *
	 * @access public
	 * @uses none
	 * @examples set_dot_item('email.mailtype','html')
	 */
	public function set_dot_item($setting,$value=null) {
		log_message('debug', 'MY_Config::set_item_dot::'.$setting);

		$this->_load_config();

		list($file,$key) = explode('.', strtolower($setting), 2);

		if ($key) {
			$this->config[$file][$key] = $value;
		} else {
			$this->config[$file] = $value;
		}

		/* allow chaining */
		return $this;
	}

	/**
	 * flush the cached data for the NEXT request
	 *
	 * @return boolean success or failure
	 *
	 * @access public
	 * @examples flush()
	 */
	public function flush() {
		log_message('debug', 'MY_Config::settings_flush');

		$this->setup = false;

		return ci('cache')->export->delete('config');
	}

	protected function _load_config() {
		if (!$this->setup) {
			$this->setup = true;
			$this->config = $this->_load_combined_config();
		}
	}

	/**
	 * configuration cache builder
	 * combined file config files, environment files, database values
	 *
	 * @return null
	 *
	 */
	protected function _load_combined_config() {
		/* load from the cache */
		$complete_config = ci('cache')->export->get('config');

		/* did we get a array? */
		if (!is_array($complete_config)) {
			/* no - so we need to build our dynamic configuration */
			$built_config = [];

			/* load the application configs */
			foreach (glob(APPPATH.'/config/*.php') as $filepath) {
				$basename = basename($filepath,'.php');

				$config = load_config($basename);

				if (is_array($config)) {
					foreach ($config as $key=>$value) {
						$built_config[strtolower($basename)][strtolower($key)] = $value;
					}
				}
			}

			/* load the database configs (settings) */
			if (parent::item('no_database_settings') !== true) {
				$db_configs = ci('o_setting_model')->for_config();

				if (is_array($db_configs)) {
					foreach ($db_configs as $record) {
						$built_config[strtolower($record->group)][strtolower($record->name)] = convert_to_real($record->value);
					}
				}
			}

			/* combined with any configuration already loaded */
			$complete_config = array_replace($this->config,$built_config);

			/* save it in the cache */
			ci('cache')->export->save('config',$complete_config);
		}

		return $complete_config;
	}
} /* end class */
