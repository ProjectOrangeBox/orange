<?php
/*
 * Orange Framework Extension
 *
 * @package	CodeIgniter / Orange
 * @author Don Myers
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 *
 * required
 * core:
 * libraries:
 * models:
 * helpers:
 * functions:
 *
 */

define('ORANGE_VERSION', '2.0.0');
define('ROOTPATHS', get_include_path());

spl_autoload_register('codeigniter_autoload');

require_once BASEPATH.'core/CodeIgniter.php';

function &ci($class=null) {
	if ($class) {

		if ($class == 'load') {
			return ci()->load;

		} elseif (ci()->load->is_loaded($class)) {
			return CI_Controller::get_instance()->$class;
		} else {

			if (codeigniter_autoload($class)) {
				return CI_Controller::get_instance()->$class;
			} else {
				die('ci('.$class.') not found');
			}
		}
	}
	return CI_Controller::get_instance();
}

function &load_class($class, $directory = 'libraries', $param = NULL) {
	static $_classes = array();

	if (count($_classes) == 0) {
		include APPPATH.'config/autoload.php';

		if (file_exists(APPPATH.'config/'.ENVIRONMENT.'/autoload.php')) {
			include APPPATH.'config/'.ENVIRONMENT.'/autoload.php';
		}

		set_include_path(ROOTPATHS.PATH_SEPARATOR.rtrim(APPPATH,'/').PATH_SEPARATOR.implode(PATH_SEPARATOR, $autoload['packages']).PATH_SEPARATOR.rtrim(BASEPATH,'/'));
	}

	if (isset($_classes[$class])) {
		return $_classes[$class];
	}

	$name = false;

	if (file_exists(BASEPATH.$directory.'/'.$class.'.php')) {
		$name = 'CI_'.$class;

		if (class_exists($name, false) === false) {
			require BASEPATH.$directory.'/'.$class.'.php';
		}
	}

	if (file_exists(ORANGEPATH.'/'.$directory.'/'.config_item('subclass_prefix').$class.'.php')) {
		$name = config_item('subclass_prefix').$class;

		if (class_exists($name, false) === false) {
			require_once ORANGEPATH.'/'.$directory.'/'.$name.'.php';
		}
	}

	if ($name === false) {
		set_status_header(503);
		echo 'Unable to locate the specified class: '.$class.'.php';
		exit(1);
	}

	is_loaded($class);

	$_classes[$class] = isset($param) ? new $name($param) : new $name();

	return $_classes[$class];
}

function codeigniter_autoload($class) {
	$uclass = ucfirst($class);

	if ($file = stream_resolve_include_path($class.'.php')) {
		require_once $file;

		return true;
	} elseif (substr($class, -6) == '_model') {
		if (stream_resolve_include_path('models/'.$uclass.'.php')) {
			ci()->load->model($class);

			return true;
		}
	} elseif (substr($class, -10) == 'Controller') {
		if ($file = stream_resolve_include_path('controllers/'.$uclass.'.php')) {
			include $file;

			return true;
		}
	} elseif (substr($class, -6) == '_trait') {
		if (substr($class, -17) == '_controller_trait') {
			if ($file = stream_resolve_include_path('controllers/traits/'.$class.'.php')) {
				include $file;

				return true;
			}
		}
		if (substr($class, -12) == '_model_trait') {
			if ($file = stream_resolve_include_path('models/traits/'.$class.'.php')) {
				include $file;

				return true;
			}
		}
		if (substr($class, -14) == '_library_trait') {
			if ($file = stream_resolve_include_path('library/traits/'.$class.'.php')) {
				include $file;

				return true;
			}
		}
	} elseif (stream_resolve_include_path('libraries/'.$uclass.'.php')) {
		ci()->load->library($class);

		return true;
	} elseif (substr($class, -10) == 'Middleware') {
		if ($file = stream_resolve_include_path('middleware/'.$uclass.'.php')) {
			include $file;

			return true;
		}
	} elseif (substr($uclass,0,9) == 'Validate_') {
		if ($file = stream_resolve_include_path('libraries/validations/'.$uclass.'.php')) {
			include $file;

			return true;
		}
	} elseif (substr($uclass,0,7) == 'Filter_') {
		if ($file = stream_resolve_include_path('libraries/filters/'.$uclass.'.php')) {
			include $file;

			return true;
		}
	}

	return false;
}

function site_url($uri = '', $protocol = NULL) {
	$uri = ci()->config->site_url($uri, $protocol);

	$paths = cache_var_export::cache('get_path', function () {
		$array = [];
		$paths = config('paths');

		foreach ($paths as $m => $t) {
			$array['{'.$m.'}'] = $t;
		}

		return ['keys' => array_keys($array), 'values' => array_values($array)];
	});

	return str_replace($paths['keys'], $paths['values'], $uri);
}

function config($setting = null, $default = null) {
	return ci()->config->item($setting,$default);
}

function esc($string) {
	return str_replace('"', '\"', $string);
}

function e($string) {
	return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function getPublicObjectVars($obj) {
  return get_object_vars($obj);
}

function l() {
	$args = func_get_args();

	foreach ($args as $idx=>$arg) {
		if (!is_scalar($arg)) {
			$args[$idx] = json_encode($arg);
		}
	}

	$build  = date('H:i:s').chr(10);

	foreach ($args as $a) {
		$build .= chr(9).$a.chr(10);
	}

	file_put_contents(ROOTPATH.'/var/logs/'.__METHOD__.'.log',$build,FILE_APPEND | LOCK_EX);
}

function unlock_session() {
	session_write_close();
}

function delete_all_cookies() {
	$past = time() - 3600;

	foreach ($_COOKIE as $key=>$value) {
    setcookie($key,$value,$past,config('config.cookie_path','/'));
	}
}

function console($var, $type = 'log') {
	echo '<script type="text/javascript">console.'.$type.'('.json_encode($var).')</script>';
}

function view($_view,$_data) {
	$_view = 'views/'.ltrim(str_replace('.php','',$_view),'/').'.php';

	$_view_file = stream_resolve_include_path($_view);

	if ($_view_file === false) {
		throw new Exception('Could not locate view "'.$_view.'"');
	}

	extract($_data, EXTR_PREFIX_INVALID, '_');

	ob_start();

	include $_view_file;

	return ob_get_clean();
}

function atomic_file_put_contents($filepath, $content) {
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

	log_message('debug', 'atomic_file_put_contents wrote '.$filepath.' '.$bytes.' bytes');

	return $bytes;
}

function remove_php_file_from_opcache($fullpath) {
	$success = (is_file($fullpath)) ? unlink($fullpath) : true;

	if (function_exists('opcache_invalidate')) {
		opcache_invalidate($filepath, true);
	} elseif (function_exists('apc_delete_file')) {
		apc_delete_file($filepath);
	}

	return $success;
}

function convert_to_real($value) {
	switch (trim(strtolower($value))) {
	case 'true':
		return true;
		break;
	case 'false':
		return false;
		break;
	case 'empty':
		return '';
		break;
	case 'null':
		return null;
		break;
	default:
		if (is_numeric($value)) {
			return (is_float($value)) ? (float) $value : (int) $value;
		}
	}

	$json = @json_decode($value, true);

	return ($json !== null) ? $json : $value;
}

function convert_to_string($value) {
	if (is_array($value)) {
		return var_export($value, true);
	}

	if ($value === true) {
		return 'true';
	}

	if ($value === false) {
		return 'false';
	}

	if ($value === null) {
		return 'null';
	}

	return (string) $value;
}

function simple_array($array, $key = 'id', $value = null) {
	$value = ($value) ? $value : $key;
	$new_array = [];

	foreach ($array as $row) {
		if (is_object($row)) {
			$new_array[$row->$key] = $row->$value;
		} else {
			$new_array[$row[$key]] = $row[$value];
		}
	}

	return $new_array;
}

function cache($key, $closure, $ttl = null) {
	if (!$cache = ci()->cache->get($key)) {
		$cache = $closure();
		$ttl = ($ttl) ? (int) $ttl : cache_ttl();
		ci()->cache->save($key, $cache, $ttl);
	}

	return $cache;
}

function cache_ttl($use_window=true) {
	$adjust = ($use_window) ? mt_rand(-15,15) : 0;
	return (ENVIRONMENT == 'development') ? 1 : $adjust + (int) config('config.cache_ttl', 60);
}

function delete_cache_by_tags($args) {
	if (is_array($args)) {
		$tags = $args;
	} elseif(strpos($args,'.') !== false) {
		$tags = explode('.', $args);
	} else {
		$tags = func_get_args();
	}

	log_message('debug', 'delete_cache_by_tags '.implode(', ', $tags));

	event::trigger('delete cache by tags', $tags);

	$cached_keys = ci()->cache->cache_info();

	if (is_array($cached_keys)) {
		foreach ($cached_keys as $key) {
			if (count(array_intersect(explode('.', $key['name']), $tags))) {
				ci()->cache->delete($key['name']);
			}
		}
	}
}

function filter_filename($str,$ext=null) {
	$str = strtolower(trim(preg_replace('#\W+#', '_', $str), '_'));

	return ($ext) ? $str.'.'.$ext : $str;
}

function filter_human($str) {
	return ucwords(str_replace('_',' ',strtolower(trim(preg_replace('#\W+#',' ', $str),' '))));
}
