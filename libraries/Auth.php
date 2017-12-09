<?php
/**
 * This package has been extended and modified
 *
 * @package	CodeIgniter / Orange
 * @author Don Myers
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 *
 * @based on Tank_auth (http://konyukhov.com/soft/)
 * @based on DX Auth by Dexcell (http://dexcell.shinsengumiteam.com/dx_auth)
 *
 * required
 * core: session, load, input,
 * libraries:
 * models:
 * helpers:
 * functions:
 *
 */
class Auth {
	protected $session_key = 'user::data';

	/**
	 * load the required models and setup the user
	 * @private
	 * @author Don Myers
	 */
	public function __construct() {
		ci()->load->model(['o_permission_model','o_role_model','o_user_model']);
		
		/* set some constants */
		define('ADMIN_ROLE_ID',config('auth.admin role id'));
		define('NOBODY_USER_ID',config('auth.nobody user id'));

		/* if this is a cli request we don't need to setup the user profile */
		if (!is_cli()) {
			$this->refresh_userdata((int)ci()->session->userdata($this->session_key));
		}

		log_message('info', 'Auth Class Initialized');
	}

	/**
	 * Login handler
	 * @author Don Myers
	 * @param  string $email users email address
	 * @param  string $password users password
	 * @return bool
	 */
	public function login($email, $password) {
		$ajax = ci()->input->is_ajax_request();

		$success = $this->_login($email, $password);

		event::trigger('auth.login', $email, $success, $ajax);

		log_message('info', 'Auth Class login');

		return $success;
	}

	/**
	 * Logout handler
	 * @author Don Myers
	 * @return bool
	 */
	public function logout() {
		event::trigger('auth.logout');
		
		/* make them a guest */
		$this->refresh_userdata(NOBODY_USER_ID);

		log_message('info', 'Auth Class logout');

		return true;
	}

	/**
	 * Refresh the users data from the database
	 * @author Don Myers
	 * @param  integer [$user_id = null] The Id of the user you want to refresh them as
	 * @return bool
	 */
	public function refresh_userdata($user_id) {
		/* double check user id is a integer greater than 0 */
		$user_id = ((int)$user_id > 0) ? (int)$user_id : NOBODY_USER_ID;

		$profile = ci()->o_user_model->get($user_id);

		/* let's make sure they are still active? */
		if ((int)$profile->is_active == 1 && $profile instanceof O_user_entity) {
			/* clear password */
			unset($profile->password);
	
			ci()->user = &$profile;
			
			ci()->session->set_userdata([$this->session_key => $profile->id]);
		} else {
			/* therefore make them nobody */
			$this->refresh_userdata(NOBODY_USER_ID);
		}

		log_message('info', 'Auth Class Refreshed');
	}

	/**
	 * The Login heavy lifter with all the tests
	 * @private
	 * @author Don Myers
	 * @param  string $email users email address
	 * @param  string $password users password
	 * @return bool
	 */
	protected function _login($login, $password) {
		/* TEST -- did they send anything in? */
		if ((strlen(trim($login)) == 0) or (strlen(trim($password)) == 0)) {
			errors::add(config('auth.empty fields error'));

			log_message('debug', 'auth->user ' . config('auth.empty fields error'));

			return false;
		}

		/* basic trigger to let listeners know we are trying to init login */
		event::trigger('user.login.init', $login);

		/* TEST -- ok does this users email exists? */
		if (!$user = ci()->o_user_model->get_user_by_email($login)) {
			log_message('debug', 'Auth Get User by email returned NULL');

			errors::add(config('auth.general failure error'));

			return false;
		}

		/* TEST -- another safety check - is user a object */
		if (!($user instanceof O_user_entity)) {
			log_message('debug', 'Auth $user not an object');

			errors::add(config('auth.general failure error'));

			return false;
		}

		/* TEST -- another safety check - is the user id a integer less than 1 */
		if ((int) $user->id === 0) {
			log_message('debug', 'Auth $user->id is 0 (no users id is 0)');

			errors::add(config('auth.general failure error'));

			return false;
		}

		/* TEST -- OK check users password with the built in password hasher */
		if (password_verify($password, $user->password) !== true) {
			/* this is the real password wrong error */
			event::trigger('user.login.fail', $login);

			log_message('debug', 'auth->user Incorrect Login and/or Password');

			errors::add(config('auth.general failure error'));

			return false;
		}

		if ((int) $user->is_active == 0) {
			/* this is the real password wrong error */
			event::trigger('user.login.in active', $login);

			log_message('debug', 'auth->user Incorrect Login and/or Password');

			errors::add(config('auth.general failure error'));

			return false;
		}

		/* attach it to CI */
		$this->refresh_userdata($user->id);
		
		return true;
	}

} /* end class */