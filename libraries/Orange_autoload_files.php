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
 * required: none
 * constants: ENVIRONMENT, APPPATH, ORANGEPATH, ROOTPATH
 */
class Orange_autoload_files {
	/**
	 * internal paths to search
	 *
	 * @var array
	 */
	protected $paths = [];

	/**
	 * where should we store the cache file
	 *
	 * @var string
	 */
	protected $cache_path;

	/**
	 * how many folder down is the root folder
	 *
	 * @var int
	 */
	protected $folder_levels;

	/**
	 * build array of classes and cache
	 *
	 * @param string $path location of the cache file
	 * @param integer $level how many folder down is the root folder?
	 *
	 */
	public function __construct($cache_path,$folder_levels=2) {
		$this->cache_path = $cache_path;
		$this->folder_levels = $folder_levels;

		/* if we are in development mode or the cache path is missing */
		if (ENVIRONMENT == 'development' || !file_exists($this->cache_path)) {
			require APPPATH.'/config/autoload.php';
			$this->paths = explode(PATH_SEPARATOR,rtrim(APPPATH,'/').PATH_SEPARATOR.implode(PATH_SEPARATOR,$autoload['packages']));
			$this->write_cache($this->build_cache($autoload));
		}
	}

	/**
	 * Insert description here
	 *
	 * @return
	 *
	 */
	public function read_cache() {
		if (!is_file($this->cache_path)) {
			throw new Exception('Orange Crush Cache File Missing?');
		}

		return include $this->cache_path;
	}

	/**
	 * Insert description here
	 *
	 * @param $array
	 *
	 * @return
	 *
	 */
	protected function write_cache($array) {
		for ($i = 0; $i < $this->folder_levels; $i++) {
			$php1 .= 'dirname(';
			$php2 .= ')';
		}

		return atomic_file_put_contents($this->cache_path,'<?php '.chr(10).chr(10).'$baseDir = '.$php1.'__DIR__'.$php2.';'.chr(10).chr(10).'return '.str_replace(chr(39).ROOTPATH,"\$baseDir.'",var_export($array, true).';'));
	}

	/**
	 * get the autoload array of class files
	 *
	 *
	 * @return array array of classes
	 *
	 */
	protected function build_cache($autoload = []) {
		$classes = [
			'model_entity'=>ORANGEPATH.'/models/Model_entity.php',
			'cache_export'=>ORANGEPATH.'/libraries/Cache_export.php',
			'cache_page'=>ORANGEPATH.'/libraries/Cache_page.php',
			'middleware_base'=>ORANGEPATH.'/libraries/Middleware_base.php',
			'validate_base'=>ORANGEPATH.'/libraries/Validate_base.php',
			'filter_base'=>ORANGEPATH.'/libraries/Filter_base.php',
		];

		$base = [
			'classes' => array_merge(
				$classes,
				$this->globr(BASEPATH,'(.*).php'),
				$this->search('/libraries/validations/','(.*)Validate_(.*).php'),
				$this->search('/libraries/pear_plugins/','(.*).php','filename'),
				$this->search('/libraries/filters/','(.*)filters/Filter_(.*).php'),
				$this->search('/middleware/','(.*)Middleware.php'),
				$this->search('/controllers/traits/','(.*)_controller_trait.php','filename'),
				$this->search('/models/traits/','(.*)_model_trait.php','filename'),
				$this->search('/library/traits/','(.*)trait.php','filename'),
				$this->search('/models/entities/','(.*)_entity.php','filename'),
				$this->search('/core/','(.*).php')
			),
			'models' => $this->search('/models/','(.*)_model.php'),
			'libraries' => array_diff_key($this->search('/libraries/','(.*).php',function($filepath) {
				$count = 0;
				str_replace(['/validations/','/pear_plugins/','/filters/','/traits/'],'',$filepath,$count);
				return (!$count) ? strtolower(basename($filepath,'.php')) : null;
			})	,$classes),
			'views' => $this->search('/views/','(.*).php',function($filepath) { return strtolower(substr($filepath,strpos($filepath,'/views/') + 7,-4)); 	}),
			'controllers' => $this->cache_controllers(),
			'configs' => $this->cache_config(),
		];

		$overriders = (isset($autoload['autoloader'])) ? $autoload['autoloader'] : [];

		/* override what was found */
		return array_replace_recursive($base,$overriders);
	}

	/**
	 * Insert description here
	 *
	 * @return
	 *
	 */
	protected function cache_controllers() {
		$paths = $this->paths;
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

		uksort($found,function($a,$b) {
			return (strlen($a) < strlen($b));
		});

		return $found;
	}

	/**
	 * Insert description here
	 *
	 * @return
	 *
	 */
	protected function cache_config() {
		$found = [];

		foreach ($this->paths as $p) {
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
	 * Insert description here
	 *
	 * @param $folder
	 * @param $match
	 * @param $options
	 *
	 * @return
	 *
	 */
	protected function search($folder,$match,$options=true) {
		$found = [];

		foreach ($this->paths as $p) {
			$found = array_merge($this->globr($p.$folder,$match,$options),$found);
		}

		return $found;
	}

	/**
	 * Insert description here
	 *
	 * @param $path
	 * @param $match
	 * @param $option
	 *
	 * @return
	 *
	 */
	protected function globr($path,$match='(.*)',$option=true) {
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
							if ($class_name = $this->find_class_name($filepath)) {
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
	 * Insert description here
	 *
	 * @param $filepath
	 * @param $lowercase
	 *
	 * @return
	 *
	 */
	protected function find_class_name($filepath,$lowercase=true) {
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

} /* end class */
