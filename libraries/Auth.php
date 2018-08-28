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
 * libraries: event, errors
 * models: o_user_model
 * helpers:
 * functions:
 * constants: NOBODY_USER_ID, ADMIN_ROLE_ID
 *
 * @show Authorization class
 */
class Auth {
	/**
	 * session key
	 *
	 * @var string
	 */
	protected $session_key = 'user::data';
	protected $config;
	protected $session;
	protected $event;
	protected $errors;
	protected $controller;

	protected $user_model;

	public function __construct(&$config=[]) {
		$this->config = &$config;

		$this->session = &ci('session');
		$this->event = &ci('event');
		$this->errors = &ci('errors');
		$this->controller = &ci();

		$this->user_model =& ci('o_user_model');

		/* define some global Constants */
		define('ADMIN_ROLE_ID',$this->config['admin role id']);
		define('NOBODY_USER_ID',$this->config['nobody user id']);

		/* attach a empty one to super object */
		$this->controller->user = &$this->user_model->get(NOBODY_USER_ID);

		/* Are we in GUI mode? */
		if (!is_cli()) {
			/* yes - is there a user id in the session? */
			$user_identifier = $this->session->userdata($this->session_key);

			/* if user identifier is empty then set the user to nobody */
			$user_identifier = (!empty($user_identifier)) ? $user_identifier : NOBODY_USER_ID;

			/* refresh the user based on the id */
			$this->refresh_userdata($user_identifier,false);
		} else {
			/* no - in CLI you have the nobody user privileges */
			$this->refresh_userdata(NOBODY_USER_ID,false);

			/* and set the user name to cli (not nobody) */
			ci('user')->username = 'cli';
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
	public function login($user_identifier, $password) {
		$success = $this->_login($user_identifier, $password);

		$this->event->trigger('auth.login', $user_identifier, $success);

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
		$this->event->trigger('auth.logout');

		$this->refresh_userdata(NOBODY_USER_ID);

		log_message('info', 'Auth Class logout');

		return true;
	}

/**
 * Refresh the current user profile based on a user id
 * you can optionally save it to the current session
 *
 * @param $user_identifier integer
 * @param $save_session boolean
 *
 * @return integer
 *
 * @access public
 *
 */
	public function refresh_userdata($user_identifier,$save_session=true) {
		log_message('debug', 'Auth::refresh_userdata::'.$user_identifier);

		$user_identifier = (!empty($user_identifier)) ? $user_identifier : NOBODY_USER_ID;

		$profile = $this->user_model->get($user_identifier);

		if ((int)$profile->is_active != 1 || !$profile instanceof O_user_entity) {
			$user_identifier = NOBODY_USER_ID;

			$profile = $this->user_model->get($user_identifier);
		}

		/* no real need to have this floating around */
		unset($profile->password);

		/* update the CodeIgniter user object to the profile */
		$this->controller->user = &$profile;

		/* should we save this profile id in the session? */
		if ($save_session) {
			$this->session->set_userdata([$this->session_key => $profile->id]);
		}

		log_message('info', 'Auth Class Refreshed');

		return $user_identifier; /* integer */
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
			$this->errors->add($this->config['empty fields error']);
			log_message('debug', 'auth->user '.config('auth.empty fields error'));
			return false;
		}

		/* Run trigger */
		$this->event->trigger('user.login.init', $login);

		/* Try to locate a user by there email */
		if (!$user = $this->user_model->get_user_by_email($login)) {
			log_message('debug', 'Auth Get User by email returned NULL');
			$this->errors->add($this->config['general failure error']);
			return false;
		}

		/* Did we get a instance of orange user entity? */
		if (!($user instanceof O_user_entity)) {
			log_message('debug', 'Auth $user not an object');
			$this->errors->add($this->config['general failure error']);
			return false;
		}

		/* Is the user id 0? There is not user 0 */
		if ((int) $user->id === 0) {
			log_message('debug', 'Auth $user->id is 0 (no users id is 0)');
			$this->errors->add($this->config['general failure error']);
			return false;
		}

		/* Verify the Password entered with what's in the user object */
		if (password_verify($password, $user->password) !== true) {
			$this->event->trigger('user.login.fail', $login);
			log_message('debug', 'auth->user Incorrect Login and/or Password');
			$this->errors->add($this->config['general failure error']);
			return false;
		}

		/* Is this user activated? */
		if ((int) $user->is_active == 0) {
			$this->event->trigger('user.login.in active', $login);
			log_message('debug', 'auth->user Incorrect Login and/or Password');
			$this->errors->add($this->config['general failure error']);
			return false;
		}

		/* ok they are good refresh the user and save to the session */
		$this->refresh_userdata($user->id);

		return true;
	}
}
