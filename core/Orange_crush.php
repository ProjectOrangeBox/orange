<?php

class Orange_crush {
	protected $paths = [];
	protected $cache_path;
	protected $root_path_tag = '#<!ROOTPATH!>#';

	public function __construct() {
		require APPPATH.'/config/autoload.php';

		$this->cache_path = ROOTPATH.'/autoload_files.php';

		$this->paths = explode(PATH_SEPARATOR,rtrim(APPPATH,'/').PATH_SEPARATOR.implode(PATH_SEPARATOR,$autoload['packages']));

		if ($_ENV['ORANGE_FILE_CACHE'] == true) {
			$this->create_cache();
		}

		set_orange_paths($this->read_cache());
	}

	public function create_cache() {
		$crush = [];

		$caches = [
			'paths'=>$this->paths,
			'system'=>$this->cache_system(),
			'controllers'=>$this->cache_controllers(),
			'models'=>$this->cache_models(),
			'views'=>$this->cache_views(),
			'libraries'=>$this->cache_libraries(),
			'validations'=>$this->cache_validations(),
			'pear_plugins'=>$this->cache_pear_plugins(),
			'filters'=>$this->cache_filters(),
			'middleware'=>$this->cache_middleware(),
			'controller_traits'=>$this->cache_controller_traits(),
			'model_traits'=>$this->cache_model_traits(),
			'library_traits'=>$this->cache_library_traits(),
			'core'=>$this->cache_core(),
		];

		$crush['caches'] = $caches;

		foreach ($caches as $key=>$array) {
			$crush['orange'] = array_merge($caches['system'],$caches['controllers'],$caches['validations'],$caches['pear_plugins'],$caches['filters'],$caches['middleware'],$caches['controller_traits'],$caches['model_traits'],$caches['library_traits'],$caches['core']);
		}

		$crush['models'] = $caches['models'];

		$crush['libraries'] = $caches['libraries'];

		$crush['views'] = $caches['views'];

		$this->write_cache($crush);

		return $crush;
	}

	protected function write_cache($array) {
		$php = '<?php '.chr(10).chr(10);
		$php .= '$baseDir = dirname(__FILE__);'.chr(10).chr(10);

		$vars = var_export($array, true);
		$vars = str_replace(chr(39).$this->root_path_tag,"\$baseDir.'",$vars);

		$php .= 'return '.$vars.';';

		return $this->atomic_file_put_contents($this->cache_path,$php);
	}

	protected function read_cache() {
		if (!is_file($this->cache_path)) {
			throw new Exception('Orange Crush Cache File Missing?');
		}

		return include $this->cache_path;
	}

	protected function atomic_file_put_contents($filepath, $content) {
		$tmpfname = tempnam(dirname($filepath), 'afpc_');

		if ($tmpfname === false) {
			throw new Exception('atomic file put contents could not create temp file');
		}

		$bytes = file_put_contents($tmpfname, $content);

		if ($bytes === false) {
			throw new Exception('atomic file put contents could not file put contents');
		}

		if (rename($tmpfname, $filepath) === false) {
			throw new Exception('atomic file put contents could not make atomic switch');
		}

		if (function_exists('opcache_invalidate')) {
			opcache_invalidate($filepath, true);
		} elseif (function_exists('apc_delete_file')) {
			apc_delete_file($filepath);
		}

		chmod($filepath, 0666);

		return $bytes;
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

							$found[strtolower($uri).'(.*)'] = $this->clean_cache_path($file->getPathname());
						}
					}
				}
			}
		}

		uksort($found,function($a,$b) {
			return (strlen($a) < strlen($b));
		});

		$found['(.*)'] = '#<!ROOTPATH!>#/application/controllers/MainController.php';

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

} /* end class */
