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
	 * session key
	 *
	 * @var string
	 */
	protected $session_key = 'user::data';

	public function __construct() {
		/* load the needed libraries and models */
		ci('load')->library('user')->model(['o_permission_model','o_role_model','o_user_model']);

		/* define some global Constants */
		define('ADMIN_ROLE_ID',config('auth.admin role id'));
		define('NOBODY_USER_ID',config('auth.nobody user id'));

		/* Are we in GUI mode? */
		if (!is_cli()) {
			/* yes - is there a user id in the session? */
			$user_id = (int)ci('session')->userdata($this->session_key);

			/* if not or it's 0 (there is no user 0) set the user to nobody */
			$user_id = ($user_id > 0) ? $user_id : NOBODY_USER_ID;

			/* refresh the user based on the id */
			$this->refresh_userdata($user_id,false);
		} else {
			/* no - in CLI you have the nobody user privileges */
			$this->refresh_userdata(NOBODY_USER_ID,false);

			/* and set the user name to cli (not nobody) */
			ci()->user->username = 'cli';
		}

		log_message('info', 'Auth Class Initialized');
	}

/**
 * Perform a login using email and password
 *
 * @param $email string
 * @param $password string
 *
 * @return boolean
 *
 * @access public
 *
 */
	public function login($email, $password) {
		$success = $this->_login($email, $password);

		ci('event')->trigger('auth.login', $email, $success);

		log_message('info', 'Auth Class login');

		return $success; /* boolean */
	}

/**
 * Perform a logout
 *
 * @return boolean
 *
 * @access public
 *
 */
	public function logout() {
		ci('event')->trigger('auth.logout');

		$this->refresh_userdata(NOBODY_USER_ID);

		log_message('info', 'Auth Class logout');

		return true;
	}

/**
 * Refresh the current user profile based on a user id
 * you can optionally save it to the current session
 *
 * @param $user_id integer
 * @param $save_session boolean
 *
 * @return integer
 *
 * @access public
 *
 */
	public function refresh_userdata($user_id,$save_session=true) {
		log_message('debug', 'Auth::refresh_userdata::'.$user_id);

		$user_id = ((int)$user_id > 0) ? (int)$user_id : NOBODY_USER_ID;

		$profile = ci('o_user_model')->get($user_id);

		if ((int)$profile->is_active != 1 || !$profile instanceof O_user_entity) {
			$user_id = NOBODY_USER_ID;
			$profile = ci('o_user_model')->get($user_id);
		}

		/* no real need to have this floating around */
		unset($profile->password);

		/* update the CodeIgniter user object to the profile */
		ci()->user = &$profile;

		/* should we save this profile id in the session? */
		if ($save_session) {
			ci('session')->set_userdata([$this->session_key => $profile->id]);
		}

		log_message('info', 'Auth Class Refreshed');

		return $user_id; /* integer */
	}

/**
 * Do actual login with multiple levels of validation
 *
 * @param $login string
 * @param $password string
 *
 * @return boolean
 *
 * @access protected
 */
	protected function _login($login, $password) {
		/* Does login and password contain anything empty values are NOT permitted for any reason */
		if ((strlen(trim($login)) == 0) or (strlen(trim($password)) == 0)) {
			ci('errors')->add(config('auth.empty fields error'));
			log_message('debug', 'auth->user '.config('auth.empty fields error'));
			return false;
		}

		/* Run trigger */
		ci('event')->trigger('user.login.init', $login);

		/* Try to locate a user by there email */
		if (!$user = ci('o_user_model')->get_user_by_email($login)) {
			log_message('debug', 'Auth Get User by email returned NULL');
			ci('errors')->add(config('auth.general failure error'));
			return false;
		}

		/* Did we get a instance of orange user entity? */
		if (!($user instanceof O_user_entity)) {
			log_message('debug', 'Auth $user not an object');
			ci('errors')->add(config('auth.general failure error'));
			return false;
		}

		/* Is the user id 0? There is not user 0 */
		if ((int) $user->id === 0) {
			log_message('debug', 'Auth $user->id is 0 (no users id is 0)');
			ci('errors')->add(config('auth.general failure error'));
			return false;
		}

		/* Verify the Password entered with what's in the user object */
		if (password_verify($password, $user->password) !== true) {
			ci('event')->trigger('user.login.fail', $login);
			log_message('debug', 'auth->user Incorrect Login and/or Password');
			ci('errors')->add(config('auth.general failure error'));
			return false;
		}

		/* Is this user activated? */
		if ((int) $user->is_active == 0) {
			ci('event')->trigger('user.login.in active', $login);
			log_message('debug', 'auth->user Incorrect Login and/or Password');
			ci('errors')->add(config('auth.general failure error'));
			return false;
		}

		/* ok they are good refresh the user and save to the session */
		$this->refresh_userdata($user->id);

		return true;
	}
}
