<?php
/**
 * MY_Config
 * The Config class provides a means to retrieve configuration preferences. These preferences can come from the default config file (application/config/config.php) or from your own custom config files.
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
 * models:
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
	 *
	 * extends the default with dot notation selection
	 *
	 * @param string $item Config item name or dot notation format
	 * @param mixed $index Index name or default if dot notation used
	 *
	 * @return string|null The configuration item or NULL if the item doesn't exist
	 *
	 * @access public
	 * @uses none
	 * @examples item('email','mailtype')
	 * @examples item('email.mailtype','html')
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

	/**
	 * flush the cached data
	 *
	 *
	 * @return boolean success or failure
	 *
	 * @access public
	 * @examples flush()
	 */
	public function flush() {
		log_message('debug', 'MY_Config::settings_flush');

		return ci('cache')->export->delete('config');
	}

	/**
	 * configuration cache builder
	 * combined file config files, environment files, database values
	 *
	 *
	 * @return null
	 *
	 */
	protected function _load_combined_config() {
		/* load from the cache */
		$built_config = ci('cache')->export->get('config');

		/* did we get a array? */
		if (!is_array($built_config)) {
			/* no - so we need to build our dynamic configuration */
			$built_config = [];
			$paths = orange_paths();

			/* load the application configs */
			foreach ($paths['configs']['root'] as $group_key=>$filepath) {
				$config = null;

				include $filepath;

				if (is_array($config)) {
					foreach ($config as $key => $value) {
						$built_config[$group_key][strtolower($key)] = $value;
					}
				}
			}

			/* load the environment configs */
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

			/* load the database configs (settings) */
			if (parent::item('no_database_settings') !== true) {
				$config = ci('o_setting_model')->for_config();

				if (is_array($config)) {
					foreach ($config as $record) {
						$built_config[strtolower($record->group)][strtolower($record->name)] = convert_to_real($record->value);
					}
				}
			}

			/* combined with any configuration already loaded */
			$built_config = $this->config + $built_config;

			/* save it in the cache */
			ci('cache')->export->save('config',$built_config);
		}

		$this->config = $built_config;
	}
} /* end class */
