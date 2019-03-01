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
 * Authorization class.
 *
 * Handles login, logout, refresh user data
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2019
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version v2.0
 * @filesource
 *
 * @uses # \o_user_model - Orange User Model
 * @uses # \session - CodeIgniter Session
 * @uses # \event - Orange event
 * @uses # \errors - Orange errors
 * @uses # \controller - CodeIgniter Controller
 * @uses # \output - CodeIgniter Output
 *
 * @config username min length `8`
 * @config username max length `32`
 * @config password regex `/((?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,32})/`
 * @config password copy `Password must be at least: 8 characters, 1 upper, 1 lower case letter, 1 number, Less than 32 characters.`
 * @config admin user id `1`
 * @config admin role id `1`
 * @config nobody user id `2`
 * @config nobody role id `2`
 * @config everyone role id `3`
 * @config login h2 `Please Sign in<h4>Using your Windows Login</h4>`
 * @config username field `Login`
 * @config empty fields error `Please enter your login credentials.`
 * @config general failure error `Incorrect Login and/or Password.`
 * @config account not active error `Your account is not active.`
 * @config user table `orange_users`
 * @config user role table `orange_user_role`
 * @config role table `orange_roles`
 * @config role permission table `orange_role_permission`
 * @config permission table `orange_permissions`
 *
 * @define NOBODY_USER_ID
 * @define ADMIN_ROLE_ID
 *
 */
class Auth
{
	/**
	 * session key
	 *
	 * @var string
	 */
	protected $session_key = 'user::data';

	/**
	 * Auth configuration array
	 *
	 * @var array
	 */
	protected $config;

	/**
	 * CodeIgniter Session Object
	 *
	 * @var array
	 */
	protected $session;

	/**
	 * CodeIgniter Event Object
	 *
	 * @var array
	 */
	protected $event;

	/**
	 * Orange Errors Object
	 *
	 * @var array
	 */
	protected $errors;

	/**
	 * CodeIgniter Controller (active)
	 *
	 * @var array
	 */
	protected $controller;

	/**
	 * Orange User Model
	 *
	 * @var array
	 */
	protected $user_model;

	/**
	 *
	 * Constructor
	 *
	 * @access public
	 *
	 * @param array $config []
	 *
	 */
	public function __construct(array &$config=[])
	{
		$this->config = &$config;

		$this->session = &ci('session');
		$this->event = &ci('event');
		$this->errors = &ci('errors');

		$this->user_model =& ci('o_user_model');

		/* define some global Constants */
		define('ADMIN_ROLE_ID', $this->config['admin role id']);
		define('NOBODY_USER_ID', $this->config['nobody user id']);
		define('EVERYONE_ROLE_ID', $this->config['everyone role id']);

		/* We all start off as nobody in life... */
		$this->switch_to_nobody();

		/* Are we in GUI mode? */
		if (!is_cli()) {
			/* yes - is there a user id in the session? */
			$user_primary_key = $this->session->userdata($this->session_key);

			if (!empty($user_primary_key)) {
				/**
				 * refresh the user based on the user identifier
				 * but don't save to the session
				 * because we already loaded it from the session
				 */
				$this->refresh_userdata($user_primary_key, false);
			}
		}

		log_message('info', 'Auth Class Initialized');
	}

	/**
	 *
	 * Switch the current user to nobody
	 *
	 * @access public
	 *
	 * @return Auth
	 *
	 */
	public function switch_to_nobody() : Auth
	{
		$this->refresh_userdata($this->config['nobody user id'], false);

		return $this;
	}

	/**
	 *
	 * Perform a login using email and password
	 *
	 * @access public
	 *
	 * @param string $user_primary_key
	 * @param string $password
	 *
	 * @return Bool
	 *
	 */
	public function login(string $user_primary_key, string $password) : Bool
	{
		$success = $this->_login($user_primary_key, $password);

		$this->event->trigger('auth.login', $user_primary_key, $success);

		log_message('info', 'Auth Class login');

		return $success; /* boolean */
	}

	/**
	 *
	 * Perform a logout
	 *
	 * @access public
	 *
	 * @return Bool
	 *
	 */
	public function logout() : Bool
	{
		log_message('info', 'Auth Class logout');

		$success = true;

		$this->event->trigger('auth.logout', $success);

		if ($success) {
			$this->switch_to_nobody();
			$this->session->set_userdata([$this->session_key => '']);
		}

		return $success;
	}

	/**
	 *
	 * Refresh the current user profile based on a user id
	 * you can optionally save it to the current session
	 *
	 * @access public
	 *
	 * @param String $user_primary_key
	 * @param Bool $save_session true
	 *
	 * @return String
	 *
	 */
	public function refresh_userdata(String $user_primary_key, Bool $save_session) : Void
	{
		log_message('debug', 'Auth::refresh_userdata::'.$user_primary_key);

		if (empty($user_primary_key)) {
			throw new \Exception('Auth session refresh user identifier empty.');
		}

		$profile = $this->user_model->get_by_primary_ignore_read_role($user_primary_key);

		if ((int)$profile->is_active === 1 && $profile instanceof O_user_entity) {
			/* no real need to have this floating around */
			unset($profile->password);

			/* Attach profile object as user "service" */
			ci('user',$profile);

			/* should we save this profile id in the session? */
			if ($save_session) {
				$this->session->set_userdata([$this->session_key => $profile->id]);
			}
		}

		log_message('info', 'Auth Class Refreshed');
	}

	/**
	 *
	 * Do actual login with multiple levels of validation
	 *
	 * @access protected
	 *
	 * @param String $login
	 * @param String $password
	 *
	 * @return Bool
	 *
	 */
	protected function _login(String $login, String $password) : Bool
	{
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
		$this->refresh_userdata($user->id, true);

		return true;
	}
} /* end class */
