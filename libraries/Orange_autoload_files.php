<?php

class Orange_autoload_files {
	protected $paths = [];
	protected $cache_path;
	protected $folder_levels = 2;

	public function __construct($path) {
		$this->cache_path = $path;

		if (ENVIRONMENT == 'development' || !file_exists($this->cache_path)) {
			require APPPATH.'/config/autoload.php';

			$this->paths = explode(PATH_SEPARATOR,rtrim(APPPATH,'/').PATH_SEPARATOR.implode(PATH_SEPARATOR,$autoload['packages']));

			$this->write_cache($this->create_cache());
		}

		orange_paths($this->read_cache());
	}

	public function create_cache() {
		/* treat as classes */
		$classes = [
			'model_entity'=>ROOTPATH.'/packages/projectorangebox/orange/models/Model_entity.php',
			'cache_export'=>ROOTPATH.'/packages/projectorangebox/orange/libraries/Cache_export.php',
			'cache_page'=>ROOTPATH.'/packages/projectorangebox/orange/libraries/Cache_page.php',
		];

		$autoload_files = [
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
			'libraries' => $this->search('/libraries/','(.*).php',function($filepath) {
				$count = 0;
				str_replace(['/validations/','/pear_plugins/','/filters/','/traits/'],'',$filepath,$count);

				return (!$count) ? strtolower(basename($filepath,'.php')) : null;
			}),
			'views' => $this->search('/views/','(.*).php',function($filepath) { return strtolower(substr($filepath,strpos($filepath,'/views/') + 7,-4)); 	}),
			'controllers' => $this->cache_controllers(),
			'configs' => $this->cache_config(),
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

	protected function write_cache($array) {
		for ($i = 0; $i < $this->folder_levels; $i++) {
			$php1 .= 'dirname(';
			$php2 .= ')';
		}

		return atomic_file_put_contents($this->cache_path,'<?php '.chr(10).chr(10).'$baseDir = '.$php1.'__DIR__'.$php2.';'.chr(10).chr(10).'return '.str_replace(chr(39).ROOTPATH,"\$baseDir.'",var_export($array, true).';'));
	}

	protected function read_cache() {
		if (!is_file($this->cache_path)) {
			throw new Exception('Orange Crush Cache File Missing?');
		}

		return include $this->cache_path;
	}

	protected function cache_controllers() {
		$a = $this->paths;
		$found = [];
		$ends_with = 'Controller.php';
		$path_section = '/controllers/';

		/* cascade backwards */
		$a = array_reverse($a,true);

		foreach ($a as $p) {
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

	protected function search($folder,$match,$options=true) {
		$found = [];

		foreach ($this->paths as $p) {
			$found = array_merge($this->globr($p.$folder,$match,$options),$found);
		}

		return $found;
	}

	protected function globr($path,$match='(.*)',$option=true) {
		$found = [];

		if (file_exists($path)) {
			$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path),RecursiveIteratorIterator::SELF_FIRST);

			foreach ($files as $f) {
				$filepath = $f->getRealPath();
				if (substr($filepath,-4) == '.php') {
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
