<?php
/**
 * Wallet
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
 * core: load, session, input
 * libraries: event
 * models:
 * helpers:
 * functions:
 *
 */
class Wallet {
	/**
	 * track if the combined cached configuration has been loaded
	 *
	 * @var boolean
	 */
	protected $redirect_messages = [];

		/**
	 * track if the combined cached configuration has been loaded
	 *
	 * @var boolean
	 */
	protected $request = [];

	/**
	 * track if the combined cached configuration has been loaded
	 *
	 * @var boolean
	 */
	protected $msg_key = 'internal::wallet::msg';

	/**
	 * track if the combined cached configuration has been loaded
	 *
	 * @var boolean
	 */
	protected $stash_key = 'internal::wallet::stash';

	/**
	 * track if the combined cached configuration has been loaded
	 *
	 * @var boolean
	 */
	protected $default_msgs = [
		'success' => 'Request Completed',
		'failed'  => 'Request Failed',
		'denied'  => 'Access Denied',
		'created' => 'Record Created',
		'updated' => 'Record Updated',
		'deleted' => 'Record Deleted',
	];

	protected $config;
	protected $load;
	protected $session;
	protected $input;
	protected $event;
	
	protected $initial_pause;
	protected $pause_for_each;

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
	public function __construct(&$config,&$ci) {
		$this->config =& $config;

		$this->session =& ci('session');
		$this->event =& ci('event');
		$this->load =& ci('load');
		$this->input =& ci('input');
	
		$this->pause_for_each = ($this->config['pause_for_each']) ?? 1000;
		$this->initial_pause = ($this->config['initial_pause']) ?? 3;
	
		$this->load->vars(['wallet_messages' => [
			'messages'       => $this->session->flashdata($this->msg_key),
			'initial_pause'  => $this->initial_pause,
			'pause_for_each' => $this->pause_for_each,
		]]);

		log_message('info', 'Wallet Class Initialized');
	}

	/**
	 * snapdata
	 * Insert description here
	 *
	 * @param $newdata
	 * @param $newval
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function snapdata($newdata = null, $newval = null) {
		$newdata = (is_array($newdata)) ? $newdata : [$newdata => $newval];

		$this->session->set_tempdata($newdata, null, 3600);

		return $this;
	}

	/**
	 * get_snapdata
	 * Insert description here
	 *
	 * @param $key
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function get_snapdata($key) {
		$data = $this->session->tempdata($key);

		$this->session->unset_tempdata($key);

		return $data;
	}

	/**
	 * keep_snapdata
	 * Insert description here
	 *
	 * @param $key
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function keep_snapdata($key) {
		return $this->session->tempdata($key);
	}

	/**
	 * msg
	 * Insert description here
	 *
	 * @param $msg
	 * @param $type
	 * @param $redirect
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function msg($msg = '', $type = 'yellow', $redirect = null) {
		$sticky = ($type == 'red' || $type == 'danger' || $type == 'warning' || $type == 'yellow');

		$this->event->trigger('wallet.msg', $msg, $type, $sticky, $redirect);

		if (is_string($redirect) || $redirect === true) {
			$redirect = (is_string($redirect)) ? $redirect : $this->input->server('HTTP_REFERER');
			$this->redirect_messages[md5(trim($msg))] = ['msg' => trim($msg), 'type' => $type, 'sticky' => $sticky];

			$this->session->set_flashdata($this->msg_key, $this->redirect_messages);

			redirect($redirect);
		} else {
			$wallet_messages = $this->load->get_var('wallet_messages');
			$current_msgs = (array) $wallet_messages['messages'];
			$current_msgs[md5(trim($msg))] = ['msg' => trim($msg), 'type' => $type, 'sticky' => $sticky];

			$this->load->vars(['wallet_messages' => [
				'messages'       => $current_msgs,
				'initial_pause'  => $this->initial_pause,
				'pause_for_each' => $this->pause_for_each,
			]]);
		}

		return $this;
	}

	/**
	 * stash
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
	public function stash() {
		$this->snapdata($this->stash_key, $this->input->request());

		return $this;
	}

	/**
	 * unstash
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
	public function unstash() {
		$stashed = $this->get_snapdata($this->stash_key);

		$_POST = (is_array($stashed)) ? $stashed : [];

		return $stashed;
	}

} /* end class */