<?php

class Orange_autoload_files {
	protected $paths = [];
	protected $cache_path;
	protected $root_path_tag = '#<!ROOTPATH!>#';

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
		$autoload_files = [
			'orange' => array_merge($this->cache_system(),$this->cache_validations(),$this->cache_pear_plugins(),$this->cache_filters(),$this->cache_middleware(),$this->cache_controller_traits(),$this->cache_model_traits(),$this->cache_library_traits(),$this->cache_core()),
			'models' => $this->cache_models(),
			'libraries' => $this->cache_libraries(),
			'views' => $this->cache_views(),
			'controllers' => $this->cache_controllers(),
		];

		return $autoload_files;
	}

	protected function write_cache($array) {
		$php = '<?php '.chr(10).chr(10);
		$php .= '$baseDir = dirname(__DIR__);'.chr(10).chr(10);
		$php .= 'return '.str_replace(chr(39).$this->root_path_tag,"\$baseDir.'",var_export($array, true)).';';

		return atomic_file_put_contents($this->cache_path,$php);
	}

	protected function read_cache() {
		if (!is_file($this->cache_path)) {
			throw new Exception('Orange Crush Cache File Missing?');
		}

		return include $this->cache_path;
	}

	protected function clean_path($path) {
		return str_replace(ROOTPATH,'',$path);
	}

	protected function clean_cache_path($path) {
		return str_replace(ROOTPATH,$this->root_path_tag,$path);
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
						$uri = $this->clean_path($file->getPathname());

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

	protected function cache_models() {
		$a = $this->paths;
		$found = [];
		$ends_with = '_model.php';
		$path_section = '/models/';

		foreach ($a as $p) {
			if (file_exists($p)) {
				$it = new RecursiveDirectoryIterator($p);

				foreach(new RecursiveIteratorIterator($it) as $file) {
					if (substr($file->getBasename(),-strlen($ends_with)) == $ends_with) {
						$uri = $this->clean_path($file->getPathname());

						$pos = strpos($uri,$path_section);

						if ($pos) {
							$uri = substr($uri,$pos+strlen($path_section),-4);

							$found[strtolower($uri)] = $this->clean_cache_path($file->getPathname());
						}
					}
				}
			}
		}

		return $found;
	}

	protected function cache_views() {
		$a = $this->paths;
		$found = [];
		$ends_with = '.php';
		$path_section = '/views/';

		/* cascade backwards */
		$a = array_reverse($a,true);

		foreach ($a as $p) {
			if (file_exists($p)) {
				$it = new RecursiveDirectoryIterator($p);

				foreach(new RecursiveIteratorIterator($it) as $file) {
					if (substr($file->getBasename(),-strlen($ends_with)) == $ends_with) {
						$uri = $this->clean_path($file->getPathname());

						$pos = strpos($uri,$path_section);

						if ($pos) {
							$uri = substr($uri,$pos+strlen($path_section),-4);

							$found[strtolower($uri)] = $this->clean_cache_path($file->getPathname());
						}
					}
				}
			}
		}

		return $found;
	}

	protected function cache_libraries() {
		$a = $this->paths;
		$found = [];
		$ends_with = '.php';
		$path_section = '/libraries/';

		foreach ($a as $p) {
			if (file_exists($p)) {
				$it = new RecursiveDirectoryIterator($p);

				foreach(new RecursiveIteratorIterator($it) as $file) {
					if (substr($file->getBasename(),-strlen($ends_with)) == $ends_with) {
						$uri = $this->clean_path($file->getPathname());

						$pos = strpos($uri,$path_section);

						if ($pos) {
							$uri = strtolower(substr($uri,$pos+strlen($path_section),-4));

							if (substr($uri,0,12) != 'validations/' && substr($uri,0,13) != 'pear_plugins/' && substr($uri,0,8) != 'filters/') {
								$found[$uri] = $this->clean_cache_path($file->getPathname());
							}
						}
					}
				}
			}
		}

		return $found;
	}

	protected function cache_validations() {
		$a = $this->paths;
		$found = [];
		$ends_with = '.php';
		$path_section = '/libraries/';

		foreach ($a as $p) {
			if (file_exists($p)) {
				$it = new RecursiveDirectoryIterator($p);

				foreach(new RecursiveIteratorIterator($it) as $file) {
					if (substr($file->getBasename(),-strlen($ends_with)) == $ends_with) {
						$uri = $this->clean_path($file->getPathname());

						$pos = strpos($uri,$path_section);

						if ($pos) {
							$uri = strtolower(substr($uri,$pos+strlen($path_section),-4));

							if (substr($uri,0,12) == 'validations/') {
								$uri = substr($uri,12);

								$found[$uri] = $this->clean_cache_path($file->getPathname());
							}
						}
					}
				}
			}
		}

		return $found;
	}

	protected function cache_pear_plugins() {
		$a = $this->paths;
		$found = [];
		$ends_with = '.php';
		$path_section = '/libraries/pear_plugins/';

		foreach ($a as $p) {
			if (file_exists($p)) {
				$it = new RecursiveDirectoryIterator($p);

				foreach(new RecursiveIteratorIterator($it) as $file) {
					if (substr($file->getBasename(),-strlen($ends_with)) == $ends_with) {
						$uri = $this->clean_path($file->getPathname());

						$pos = strpos($uri,$path_section);

						if ($pos) {
							$uri = strtolower(substr($uri,$pos+strlen($path_section),-4));

							if (substr($uri,0,13) != 'pear_plugins/') {
								$found[$uri] = $this->clean_cache_path($file->getPathname());
							}
						}
					}
				}
			}
		}

		return $found;
	}

	protected function cache_filters() {
		$a = $this->paths;
		$found = [];
		$ends_with = '.php';
		$path_section = '/libraries/filters/';

		foreach ($a as $p) {
			if (file_exists($p)) {
				$it = new RecursiveDirectoryIterator($p);

				foreach(new RecursiveIteratorIterator($it) as $file) {
					if (substr($file->getBasename(),-strlen($ends_with)) == $ends_with) {
						$uri = $this->clean_path($file->getPathname());

						$pos = strpos($uri,$path_section);

						if ($pos) {
							$uri = strtolower(substr($uri,$pos+strlen($path_section),-4));

							if (substr($uri,0,8) != 'filters/') {
								$found[$uri] = $this->clean_cache_path($file->getPathname());
							}
						}
					}
				}
			}
		}

		return $found;
	}

	protected function cache_middleware() {
		$a = $this->paths;
		$found = [];
		$ends_with = '.php';
		$path_section = '/middleware/';

		foreach ($a as $p) {
			if (file_exists($p)) {
				$it = new RecursiveDirectoryIterator($p);

				foreach(new RecursiveIteratorIterator($it) as $file) {
					if (substr($file->getBasename(),-strlen($ends_with)) == $ends_with) {
						$uri = $this->clean_path($file->getPathname());

						$pos = strpos($uri,$path_section);

						if ($pos) {
							$found[strtolower(substr($uri,$pos+strlen($path_section),-4))] = $this->clean_cache_path($file->getPathname());
						}
					}
				}
			}
		}

		return $found;
	}

	protected function cache_controller_traits() {
		$a = $this->paths;
		$found = [];
		$ends_with = '.php';
		$path_section = '/controllers/traits/';

		foreach ($a as $p) {
			if (file_exists($p)) {
				$it = new RecursiveDirectoryIterator($p);

				foreach(new RecursiveIteratorIterator($it) as $file) {
					if (substr($file->getBasename(),-strlen($ends_with)) == $ends_with) {
						$uri = $this->clean_path($file->getPathname());

						$pos = strpos($uri,$path_section);

						if ($pos) {
							$found[strtolower(substr($uri,$pos+strlen($path_section),-4))] = $this->clean_cache_path($file->getPathname());
						}
					}
				}
			}
		}

		return $found;
	}

	protected function cache_model_traits() {
		$a = $this->paths;
		$found = [];
		$ends_with = '.php';
		$path_section = '/models/traits/';

		foreach ($a as $p) {
			if (file_exists($p)) {
				$it = new RecursiveDirectoryIterator($p);

				foreach(new RecursiveIteratorIterator($it) as $file) {
					if (substr($file->getBasename(),-strlen($ends_with)) == $ends_with) {
						$uri = $this->clean_path($file->getPathname());

						$pos = strpos($uri,$path_section);

						if ($pos) {
							$found[strtolower(substr($uri,$pos+strlen($path_section),-4))] = $this->clean_cache_path($file->getPathname());
						}
					}
				}
			}
		}

		return $found;
	}

	protected function cache_library_traits() {
		$a = $this->paths;
		$found = [];
		$ends_with = '.php';
		$path_section = '/library/traits/';

		foreach ($a as $p) {
			if (file_exists($p)) {
				$it = new RecursiveDirectoryIterator($p);

				foreach(new RecursiveIteratorIterator($it) as $file) {
					if (substr($file->getBasename(),-strlen($ends_with)) == $ends_with) {
						$uri = $this->clean_path($file->getPathname());

						$pos = strpos($uri,$path_section);

						if ($pos) {
							$uri = strtolower(substr($uri,$pos+strlen($path_section),-4));

							$found[$uri] = $this->clean_cache_path($file->getPathname());
						}
					}
				}
			}
		}

		return $found;
	}

	protected function cache_core() {
		$a = $this->paths;
		$found = [];
		$ends_with = '.php';
		$path_section = '/core/';

		foreach ($a as $p) {
			if (file_exists($p)) {
				$it = new RecursiveDirectoryIterator($p);

				foreach(new RecursiveIteratorIterator($it) as $file) {
					if (substr($file->getBasename(),-strlen($ends_with)) == $ends_with) {
						$uri = $this->clean_path($file->getPathname());

						$pos = strpos($uri,$path_section);

						if ($pos) {
							$uri = strtolower(substr($uri,$pos+strlen($path_section),-4));

							$found[$uri] = $this->clean_cache_path($file->getPathname());
						}
					}
				}
			}
		}

		return $found;
	}

	protected function cache_system() {
		$found = [];

		$files = glob(BASEPATH.'core/*.php');

		foreach ($files as $f) {
			$found['CI_'.basename($f,'.php')] = $this->clean_cache_path($f);
		}

		$files = glob(BASEPATH.'libraries/*.php');

		foreach ($files as $f) {
			$found['CI_'.basename($f,'.php')] = $this->clean_cache_path($f);
		}

		return $found;
	}

	protected function cache_config() {
		$found = [];

		$files = glob(APPPATH.'config/*.php');

		foreach ($files as $file) {
			$found[strtolower(basename($file,'.php'))] = $this->clean_cache_path($file);
		}

		$folders = glob(APPPATH.'config/*',GLOB_ONLYDIR);

		foreach ($folders as $folder) {
			$files = glob($folder.'/*.php');

			foreach ($files as $file) {
				$found['env_'.strtolower(basename($folder))][strtolower(basename($file,'.php'))] = $this->clean_cache_path($file);
			}
		}

		return $found;
	}

} /* end class */
