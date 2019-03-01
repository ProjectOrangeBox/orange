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
 * Extension to CodeIgniter Config Class
 *
 * `dot_item_lookup($keyvalue,$default)` lookup configuration using dot notation with optional default
 *
 * `set_dot_item($name,$value)` set non permanent value in config
 *
 * `flush()` flush the cached configuration
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2019
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version v2.0
 * @filesource
 *
 * @uses # o_setting_model - Orange Settings Model
 * @uses # export cache - Orange Export Cache
 * @uses # load_config() - Orange Config File Loader
 * @uses # convert_to_real() - Orange convert string values into PHP real values where possible
 *
 * @config no_database_settings boolean
 *
 */

class MY_Config extends \CI_Config
{
	/**
	 * track if the combined cached configuration has been loaded
	 *
	 * @var boolean
	 */
	protected $setup = false;

	/**
	 *
	 * Provides dot notation selection of configuration values
	 * this is the "recommended" way to make sure you get database values as well
	 *
	 * #### Example
	 * ```php
	 * $value = ci('config')->dot_item('email.protocol','sendmail');
	 * ```
	 * @access public
	 *
	 * @param string $setting filename.key
	 * @param $default null
	 *
	 * @return mixed
	 *
	 */
	public function dot_item(string $setting, $default=null)
	{
		log_message('debug', 'MY_Config::item_dot::'.$setting);

		/* have we loaded the config? */
		$this->_load_config();

		$value = $default;
		$section = false;

		if (strpos($setting, '.')) {
			list($file, $key) = explode('.', $setting, 2);
		} else {
			$file = $setting;
			$key = false;
		}

		$file = $this->normalize_section($file);

		if (isset($this->config[$file])) {
			$section = $this->config[$file];
		}

		if ($key) {
			$key = $this->normalize_key($key);

			if (isset($section[$key])) {
				$value = $section[$key];
			}
		} elseif ($section) {
			$value = $section;
		}

		return $value;
	}

	/**
	 *
	 * Change or Add a dot notation config value
	 * NOT Saved between requests
	 *
	 * @access public
	 *
	 * @param string $setting
	 * @param $value null
	 *
	 * @return MY_Config
	 *
	 */
	public function set_dot_item(string $setting, $value=null) : MY_Config
	{
		log_message('debug', 'MY_Config::set_item_dot::'.$setting);

		/* have we loaded the config? */
		$this->_load_config();

		list($file, $key) = explode('.', strtolower($setting), 2);

		if ($key) {
			$this->config[$this->normalize_section($file)][$this->normalize_key($key)] = $value;
		} else {
			$this->config[$this->normalize_section($file)] = $value;
		}

		/* allow chaining */
		return $this;
	}

	/**
	 *
	 * Flush the cached data for the NEXT request
	 *
	 * @access public
	 *
	 * @throws
	 * @return bool
	 *
	 */
	public function flush() : bool
	{
		log_message('debug', 'MY_Config::settings_flush');

		$this->setup = false;

		return ci('cache')->export->delete('config');
	}

	/**
	 *
	 * Load the configuration if it's not already
	 *
	 * @access protected
	 *
	 * @return void
	 *
	 */
	protected function _load_config() : void
	{
		if (!$this->setup) {
			$this->setup = true;
			$this->config = $this->_load_combined_config();
		}
	}

	/**
	 *
	 * Load the combined Application, Environmental, Database Configuration values
	 *
	 * @access protected
	 *
	 * @param
	 *
	 * @return array
	 *
	 */
	protected function _load_combined_config() : array
	{
		/* load from the cache */
		$complete_config = ci('cache')->export->get('config');

		/* did we get a array? */
		if (!is_array($complete_config)) {
			/* no - so we need to build our dynamic configuration */
			$built_config = [];

			/* load the application configs */
			foreach (glob(APPPATH.'/config/*.php') as $filepath) {
				$basename = basename($filepath, '.php');

				$config = load_config($basename);

				if (is_array($config)) {
					foreach ($config as $key=>$value) {
						$built_config[$this->normalize_section($basename)][$this->normalize_key($key)] = $value;
					}
				}
			}

			/* load the database configs (settings) */
			if (parent::item('no_database_settings') !== true) {
				$db_configs = ci('o_setting_model')->get_enabled();

				if (is_array($db_configs)) {
					foreach ($db_configs as $record) {
						$built_config[$this->normalize_section($record->group)][$this->normalize_key($record->name)] = convert_to_real($record->value);
					}
				}
			}

			/* combined with any configuration already loaded */
			$complete_config = array_replace($this->config, $built_config);

			/* save it in the cache */
			ci('cache')->export->save('config', $complete_config);
		}

		return $complete_config;
	}

	protected function normalize_section(string $string) : string
	{
		return str_replace(['_','-'], ' ', strtolower($string));
	}

	protected function normalize_key(string $string) : string
	{
		return strtolower($string);
	}
} /* end class */
