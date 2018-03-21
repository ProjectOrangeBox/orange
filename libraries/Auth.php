<?php
/**
 * Auth
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
 * core: load, session
 * libraries: user, event, errors
 * models: o_permission_model, o_role_model, o_user_model
 * helpers:
 * functions:
 *
 */
class Auth {
	/**
	 * track if the combined cached configuration has been loaded
	 *
	 * @var boolean
	 */
	protected $session_key = 'user::data';

/**
 * __construct
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
	public function __construct() {
		ci('load')->library('user')->model(['o_permission_model','o_role_model','o_user_model']);
		define('ADMIN_ROLE_ID',config('auth.admin role id'));
		define('NOBODY_USER_ID',config('auth.nobody user id'));
		if (!is_cli()) {
			$user_id = (int)ci('session')->userdata($this->session_key);
			$user_id = ($user_id > 0) ? $user_id : NOBODY_USER_ID;
			$this->refresh_userdata($user_id,false);
		} else {
			$this->refresh_userdata(NOBODY_USER_ID,false);
			ci()->user->username = 'cli';
		}
		log_message('info', 'Auth Class Initialized');
	}

/**
 * login
 * Insert description here
 *
 * @param $email
 * @param $password
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function login($email, $password) {
		$success = $this->_login($email, $password);
		ci('event')->trigger('auth.login', $email, $success);
		log_message('info', 'Auth Class login');
		return $success;
	}

/**
 * logout
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
	public function logout() {
		ci('event')->trigger('auth.logout');
		$this->refresh_userdata(NOBODY_USER_ID);
		log_message('info', 'Auth Class logout');
		return true;
	}

/**
 * refresh_userdata
 * Insert description here
 *
 * @param $user_id
 * @param $save_session
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	public function refresh_userdata($user_id,$save_session=true) {
		log_message('debug', 'Auth::refresh_userdata::'.$user_id);
		$user_id = ((int)$user_id > 0) ? (int)$user_id : NOBODY_USER_ID;
		$profile = ci('o_user_model')->get($user_id);
		if ((int)$profile->is_active != 1 || !$profile instanceof O_user_entity) {
			$user_id = NOBODY_USER_ID;
			$profile = ci('o_user_model')->get($user_id);
		}
		unset($profile->password);
		ci()->user = &$profile;
		if ($save_session) {
			ci('session')->set_userdata([$this->session_key => $profile->id]);
		}
		log_message('info', 'Auth Class Refreshed');
		return $user_id;
	}

/**
 * _login
 * Insert description here
 *
 * @param $login
 * @param $password
 *
 * @return
 *
 * @access
 * @static
 * @throws
 * @example
 */
	protected function _login($login, $password) {
		if ((strlen(trim($login)) == 0) or (strlen(trim($password)) == 0)) {
			ci('errors')->add(config('auth.empty fields error'));
			log_message('debug', 'auth->user '.config('auth.empty fields error'));
			return false;
		}
		ci('event')->trigger('user.login.init', $login);
		if (!$user = ci('o_user_model')->get_user_by_email($login)) {
			log_message('debug', 'Auth Get User by email returned NULL');
			ci('errors')->add(config('auth.general failure error'));
			return false;
		}
		if (!($user instanceof O_user_entity)) {
			log_message('debug', 'Auth $user not an object');
			ci('errors')->add(config('auth.general failure error'));
			return false;
		}
		if ((int) $user->id === 0) {
			log_message('debug', 'Auth $user->id is 0 (no users id is 0)');
			ci('errors')->add(config('auth.general failure error'));
			return false;
		}
		if (password_verify($password, $user->password) !== true) {
			ci('event')->trigger('user.login.fail', $login);
			log_message('debug', 'auth->user Incorrect Login and/or Password');
			ci('errors')->add(config('auth.general failure error'));
			return false;
		}
		if ((int) $user->is_active == 0) {
			ci('event')->trigger('user.login.in active', $login);
			log_message('debug', 'auth->user Incorrect Login and/or Password');
			ci('errors')->add(config('auth.general failure error'));
			return false;
		}
		$this->refresh_userdata($user->id);
		return true;
	}
}
