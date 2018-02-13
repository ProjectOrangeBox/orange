<?php
/**
 * Orange_autoload_files
 * Insert description here
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2018
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version 2.0
 *
 * required
 * core:
 * libraries:
 * models:
 * helpers:
 * functions:
 *
 */
class Orange_autoload_files {
	/**
	 * track if the combined cached configuration has been loaded
	 *
	 * @var boolean
	 */
	static protected $paths = [];

	/**
	 * track if the combined cached configuration has been loaded
	 *
	 * @var boolean
	 */
	static protected $cache_path;

	/**
	 * track if the combined cached configuration has been loaded
	 *
	 * @var boolean
	 */
	static protected $folder_levels = 2;

/**
 * load
 * Insert description here
 *
 * @param $path
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	static public function load($path) {
		self::$cache_path = $path;
		if (ENVIRONMENT == 'development' || !file_exists(self::$cache_path)) {
			require APPPATH.'/config/autoload.php';
			self::$paths = explode(PATH_SEPARATOR,rtrim(APPPATH,'/').PATH_SEPARATOR.implode(PATH_SEPARATOR,$autoload['packages']));
			self::write_cache(self::create_cache());
		}
		orange_paths(self::read_cache());
	}

/**
 * create_cache
 * Insert description here
 *
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	static public function create_cache() {
		$classes = [
			'model_entity'=>ROOTPATH.'/packages/projectorangebox/orange/models/Model_entity.php',
			'cache_export'=>ROOTPATH.'/packages/projectorangebox/orange/libraries/Cache_export.php',
			'cache_page'=>ROOTPATH.'/packages/projectorangebox/orange/libraries/Cache_page.php',
			'middleware_base'=>ROOTPATH.'/packages/projectorangebox/orange/libraries/Middleware_base.php',
			'validate_base'=>ROOTPATH.'/packages/projectorangebox/orange/libraries/Validate_base.php',
			'filter_base'=>ROOTPATH.'/packages/projectorangebox/orange/libraries/Filter_base.php',
		];
		$autoload_files = [
			'classes' => array_merge(
				$classes,
				self::globr(BASEPATH,'(.*).php'),
				self::search('/libraries/validations/','(.*)Validate_(.*).php'),
				self::search('/libraries/pear_plugins/','(.*).php','filename'),
				self::search('/libraries/filters/','(.*)filters/Filter_(.*).php'),
				self::search('/middleware/','(.*)Middleware.php'),
				self::search('/controllers/traits/','(.*)_controller_trait.php','filename'),
				self::search('/models/traits/','(.*)_model_trait.php','filename'),
				self::search('/library/traits/','(.*)trait.php','filename'),
				self::search('/models/entities/','(.*)_entity.php','filename'),
				self::search('/core/','(.*).php')
			),
			'models' => self::search('/models/','(.*)_model.php'),
/**
 * $filepath
 * Insert description here
 *
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
			'libraries' => array_diff_key(self::search('/libraries/','(.*).php',function($filepath) {
				$count = 0;
				str_replace(['/validations/','/pear_plugins/','/filters/','/traits/'],'',$filepath,$count);
				return (!$count) ? strtolower(basename($filepath,'.php')) : null;
			})	,$classes),
/**
 * $filepath
 * Insert description here
 *
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
			'views' => self::search('/views/','(.*).php',function($filepath) { return strtolower(substr($filepath,strpos($filepath,'/views/') + 7,-4)); 	}),
			'controllers' => self::cache_controllers(),
			'configs' => self::cache_config(),
		];
		if (1 == 0) {
			echo '<pre>';
			foreach ($autoload_files['classes'] as $c=>$f) {
				echo $c.' => '.$f.chr(10);
			}
			die();
		}
		return $autoload_files;
	}

/**
 * write_cache
 * Insert description here
 *
 * @param $array
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	static protected function write_cache($array) {
		for ($i = 0; $i < self::$folder_levels; $i++) {
			$php1 .= 'dirname(';
			$php2 .= ')';
		}
		return atomic_file_put_contents(self::$cache_path,'<?php '.chr(10).chr(10).'$baseDir = '.$php1.'__DIR__'.$php2.';'.chr(10).chr(10).'return '.str_replace(chr(39).ROOTPATH,"\$baseDir.'",var_export($array, true).';'));
	}

/**
 * read_cache
 * Insert description here
 *
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	static protected function read_cache() {
		if (!is_file(self::$cache_path)) {
			throw new Exception('Orange Crush Cache File Missing?');
		}
		return include self::$cache_path;
	}

/**
 * cache_controllers
 * Insert description here
 *
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	static protected function cache_controllers() {
		$paths = self::$paths;
		$found = [];
		$ends_with = 'Controller.php';
		$path_section = '/controllers/';
		$paths = array_reverse($paths,true);
		foreach ($paths as $p) {
			if (file_exists($p)) {
				$it = new RecursiveDirectoryIterator($p);
				foreach(new RecursiveIteratorIterator($it) as $file) {
					if (substr($file->getBasename(),-strlen($ends_with)) == $ends_with) {
						$uri = str_replace(ROOTPATH,'',$file->getPathname());
						$pos = strpos($uri,$path_section);
						if ($pos) {
							$uri = substr($uri,$pos+strlen($path_section),-14);
							$controller_file = $file->getPathname();
							$cntr = str_replace(ROOTPATH.'/','',dirname(dirname($controller_file)));
							$where_is_controller = strpos($cntr,'/controllers');
							$rec['package'] = ($where_is_controller > 0) ? substr($cntr,0,$where_is_controller).'/' : $cntr.'/';
							$rec['directory'] = str_replace(ROOTPATH,'../..',dirname($controller_file)).'/';
							$rec['controller'] = $controller_file;
							$rec['clean_controller'] = basename($controller_file,'Controller.php');
							$found[strtolower($uri).'(.*)'] = $rec;
						}
					}
				}
			}
		}
/**
 * $a
 * Insert description here
 *
 * @param $b
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
		uksort($found,function($a,$b) {
			return (strlen($a) < strlen($b));
		});
		return $found;
	}

/**
 * cache_config
 * Insert description here
 *
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	static protected function cache_config() {
		$found = [];
		foreach (self::$paths as $p) {
			$files = glob($p.'/config/*.php');
			foreach ($files as $file) {
				$found['root'][strtolower(basename($file,'.php'))] = $file;
			}
			$files = glob($p.'/config/'.ENVIRONMENT.'/*.php');
			foreach ($files as $file) {
				$found[ENVIRONMENT][strtolower(basename($file,'.php'))] = $file;
			}
		}
		return $found;
	}

/**
 * search
 * Insert description here
 *
 * @param $folder
 * @param $match
 * @param $options
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	static protected function search($folder,$match,$options=true) {
		$found = [];
		foreach (self::$paths as $p) {
			$found = array_merge(self::globr($p.$folder,$match,$options),$found);
		}
		return $found;
	}

/**
 * globr
 * Insert description here
 *
 * @param $path
 * @param $match
 * @param $option
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	static protected function globr($path,$match='(.*)',$option=true) {
		$found = [];
		if (file_exists($path)) {
			$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path),RecursiveIteratorIterator::SELF_FIRST);
			foreach ($files as $f) {
				if ($f->getExtension() == 'php') {
					$filepath = $f->getRealPath();
					$matches = [];
					if (preg_match('#'.$match.'#', $filepath, $matches)) {
						if ($option === 'filename') {
							$found[strtolower(basename($filepath,'.php'))] = $filepath;
						} elseif ($option === true) {
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
 * find_class_name
 * Insert description here
 *
 * @param $filepath
 * @param $lowercase
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	static protected function find_class_name($filepath,$lowercase=true) {
		$class = '';
		$tokens = token_get_all(file_get_contents($filepath));
		for ($i = 0;$i<count($tokens);$i++) {
			if ($tokens[$i][0] === T_CLASS) {
				for ($j=$i+1;$j<count($tokens);$j++) {
					if ($tokens[$j] === '{') {
						$class = $tokens[$i+2][1];
					}
				}
			}
		}
		return ($lowercase) ? strtolower($class) : $class;
	}
}
