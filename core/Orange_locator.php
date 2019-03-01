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
 * File Locator Class
 *
 * Locates and caches the location of the CodeIgniter / Orange Files
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2019
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version v2.0
 * @filesource
 *
 * @uses atomic_file_put_contents()
 *
 * @constant ENVIRONMENT
 * @constant APPPATH
 * @constant ORANGEPATH
 * @constant ROOTPATH
 *
 */

class Orange_locator
{
	/**
	 * Paths to search
	 *
	 * @var Array
	 */
	protected static $paths = [];

	/**
	 * Where should we store the cache file
	 *
	 * @var string
	 */
	protected static $cache_path;

	/**
	 * How many folder down is the root folder
	 *
	 * @var int
	 */
	protected static $folder_levels;

	/**
	 * Actual file array
	 *
	 * @var array
	 */
	protected static $array = [];

	/**
	 * build array of classes and cache
	 *
	 * @param string $path location of the cache file
	 * @param integer $level how many folder down is the root folder?
	 *
	 */
	public static function load(string $cache_path, int $folder_levels=2) : array
	{
		self::$cache_path = $cache_path;
		self::$folder_levels = $folder_levels;

		/* if we are in development mode or the cache path is missing */
		if (!file_exists(self::$cache_path)) {
			/* load $autoload config variable */
			$autoload = load_config('autoload', 'autoload');
			
			array_unshift($autoload['packages'], rtrim(APPPATH, '/'));

			self::$paths = array_unique($autoload['packages']);

			self::$array = self::build_cache($autoload);

			self::write_cache(self::$array);
		} else {
			self::$array = include self::$cache_path;
		}

		return self::$array;
	}

	public static function controllers() : array
	{
		return self::$array['controllers'];
	}

	public static function views() : array
	{
		return self::$array['views'];
	}

	public static function view(string $file) : String
	{
		return self::paths('views', $file);
	}

	public static function classes() : array
	{
		return self::$array['classes'];
	}

	public static function class(string $file) : String
	{
		return self::paths('classes', $file);
	}

	public static function append(string $section, string $file, string $value) : void
	{
		self::$array[$section] = [$file=>$value] + self::$array[$section];
	}

	protected static function paths(string $section=null, string $file=null)
	{
		$file = strtolower($file);

		return (isset(self::$array[$section][$file])) ? self::$array[$section][$file] : false;
	}

	/**
	 * Insert description here
	 *
	 * @param $array
	 *
	 * @return
	 *
	 */
	protected static function write_cache(array $array)
	{
		$php1 = $php2 = '';
		
		/* traverse "up" this many folder to create $baseDir */
		for ($i = 0; $i < self::$folder_levels; $i++) {
			$php1 .= 'dirname(';
			$php2 .= ')';
		}

		return atomic_file_put_contents(self::$cache_path, '<?php '.chr(10).chr(10).'$baseDir = '.$php1.'__DIR__'.$php2.';'.chr(10).chr(10).'return '.str_replace(chr(39).ROOTPATH, "\$baseDir.'", str_replace("stdClass::__set_state", "(object)", var_export($array, true)).';'));
	}

	/**
	 * Get the autoload array of class files
	 *
	 * @return array
	 *
	 */
	protected static function build_cache($autoload = [])
	{
		$override = (is_array($autoload['override'])) ? $autoload['override'] : [];

		$cache = [
			'classes' => array_replace(
				self::search('/libraries/', '(.*).php', 'filename'),
				self::search('/middleware/', '(.*)Middleware.php', 'filename'),
				self::search('/controllers/traits/', '(.*)_trait.php', 'filename'),
				self::search('/models/', '(.*).php', 'filename'),
				self::search('/core/', '(.*).php', 'filename'),
				self::globr(BASEPATH, '(.*).php', 'class_name') /* add the CodeIgniter system folder */
			),
			'views' => self::search('/views/', '(.*).php', function ($filepath) {
				return strtolower(substr($filepath, strpos($filepath, '/views/') + 7, -4));
			}),
			'controllers' => self::cache_controllers(),
			/* 'config' => self::cache_config, */
		];

		return (count($override)) ? array_replace_recursive($cache, $override) : $cache;
	}

	/**
	 * Get the autoload array of controllers
	 *
	 * @return array
	 *
	 */
	protected static function cache_controllers()
	{
		$paths = self::$paths;
		$found = [];
		$ends_with = 'Controller.php';
		$path_section = '/controllers/';
		$paths = array_reverse($paths, true);

		foreach ($paths as $p) {
			if (file_exists($p)) {
				foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($p)) as $file) {
					if (substr($file->getBasename(), -strlen($ends_with)) == $ends_with) {
						$uri = str_replace(ROOTPATH, '', $file->getPathname());
						$pos = strpos($uri, $path_section);
						if ($pos) {
							$uri = substr($uri, $pos+strlen($path_section), -14);
							$controller_file = $file->getPathname();
							$cntr = str_replace(ROOTPATH.'/', '', dirname(dirname($controller_file)));
							$where_is_controller = strpos($cntr, '/controllers');
							$rec['package'] = ($where_is_controller > 0) ? substr($cntr, 0, $where_is_controller).'/' : $cntr.'/';
							$rec['directory'] = str_replace(ROOTPATH, '../..', dirname($controller_file)).'/';
							$rec['controller'] = $controller_file;
							$rec['clean_controller'] = basename($controller_file, 'Controller.php');
							$found[strtolower($uri).'(.*)'] = $rec;
						}
					}
				}
			}
		}

		/* sort by size longer regular expressions first */
		uksort($found, function ($a, $b) {
			return (strlen($a) < strlen($b));
		});

		/* capture everything else - 404 */

		/*
				'main(.*)' =>
				array (
					'package' => 'application/',
					'directory' => '../../application/controllers/',
					'controller' => $baseDir.'/application/controllers/MainController.php',
					'clean_controller' => 'Main',
				),
		*/

		$route = load_config('routes', 'route');

		list($class404, $method404) = explode('/', $route['404_override']);

		$found = $found + [
			'(.*)'=> [
				'package' => 'application/',
				'directory' => '../../application/controllers/',
				'controller' => ROOTPATH.'/application/controllers/'.$class404.'Controller.php',
				'clean_controller' => ucfirst($class404),
				'method'=>$method404,
			]
		];

		return $found;
	}

	/**
	 * Get the autoload array of config files
	 *
	 * @return array
	 *
	 */
	protected static function cache_config()
	{
		$found = [];

		foreach (self::$paths as $p) {
			$files = glob($p.'/config/*.php');

			foreach ($files as $file) {
				$found['root'][strtolower(basename($file, '.php'))] = $file;
			}

			$files = glob($p.'/config/'.ENVIRONMENT.'/*.php');

			foreach ($files as $file) {
				$found[ENVIRONMENT][strtolower(basename($file, '.php'))] = $file;
			}
		}

		return $found;
	}

	/**
	 * Do Search
	 *
	 * @param $folder
	 * @param $match
	 * @param $options
	 *
	 * @return array
	 *
	 */
	protected static function search($folder, $match, $options=true)
	{
		$found = [];

		foreach (self::$paths as $p) {
			$found = array_merge(self::globr($p.$folder, $match, $options), $found);
		}

		return $found;
	}

	/**
	 * Do recusive file search
	 *
	 * @param $path
	 * @param $match
	 * @param $option
	 *
	 * @return array
	 *
	 */
	protected static function globr($path, $match='(.*)', $option='filename')
	{
		$found = [];

		if (file_exists($path)) {
			$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::SELF_FIRST);
			foreach ($files as $f) {
				if ($f->getExtension() == 'php') {
					$filepath = $f->getRealPath();
					$matches = [];
					if (preg_match('#'.$match.'#', $filepath, $matches)) {
						if ($option === 'filename') {
							$found[strtolower(basename($filepath, '.php'))] = $filepath;
						} elseif ($option === 'class_name') {
							if ($class_name = self::find_class_name($filepath)) {
								$found[$class_name] = $filepath;
							}
						} elseif (is_callable($option)) {
							if ($class_name = $option($filepath)) {
								$found[$class_name] = $filepath;
							}
						} else {
							$found[] = $filepath;
						}
					}
				}
			}
		}

		return $found;
	}

	/**
	 * Return class name as key
	 *
	 * @param $filepath
	 * @param $lowercase
	 *
	 * @return string
	 *
	 */
	protected static function find_class_name($filepath, $lowercase=true)
	{
		$fp = fopen($filepath, 'r');
		$class = $buffer = '';
		$i = 0;
		while (!$class) {
			if (feof($fp)) {
				break;
			}

			$buffer .= fread($fp, 512);

			if (preg_match('/class\s+(\w+)(.*)?\{/', $buffer, $matches)) {
				$class = $matches[1];
				break;
			}
		}

		return ($lowercase) ? strtolower($class) : $class;
	}

	/* spl autoloader */
	public static function autoload($class)
	{
		/* search classes array in the autoload file class and load if exists returning false or path of found file */
		if ($path = self::class($class)) {
			include_once $path;

			return true;
		}

		/* can't find this class file notify the autoload (return false) to let somebody else have a shot */
		return false;
	}
} /* end class */
