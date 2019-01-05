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
	protected $redirect_messages = [];
	protected $msg_key = 'internal::wallet::msg';
	protected $view_variable = 'wallet_messages';

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
	protected $http_referer;
	protected $event;

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
	public function __construct(&$config=[]) {
		$this->config = &$config;

		$this->session = &ci('session');
		$this->event = &ci('event');
		$this->load = &ci('load');

		$this->http_referer = ci('input')->server('HTTP_REFERER');
		$this->sticky_types = ($this->config['sticky_types']) ?? ['red','danger','warning','yellow'];

		/* set the view variable if any messages are available */
		$this->set_view_variable($this->session->flashdata($this->msg_key));

		log_message('info', 'Wallet Class Initialized');
	}

	/**
	 * msg
	 * Add a msg to the current page variable
	 * - or -
	 * Add a msg as a session flash message and redirect
	 *
	 * @param $msg
	 * @param $type
	 * @param $redirect
	 *
	 * @return $this
	 *
	 */
	public function msg($msg = '', $type = 'yellow', $redirect = null)
	{
		/* is this type sticky? - use names not colors - colors support for legacy code */
		$sticky = in_array($type,$this->sticky_types);

		/* trigger a event incase they need to do something */
		$this->event->trigger('wallet.msg', $msg, $type, $sticky, $redirect);

		/* is this a redirect */
		if (is_string($redirect)) {
			$this->redirect($msg,$type,$sticky,$redirect);
		} elseif ($redirect === true) {
			$this->redirect($msg,$type,$sticky,$this->http_referer);
		} else {
			$this->add2page($msg,$type,$sticky);
		}

		return $this;
	}

	public function msgs($array,$type='blue')
	{
		foreach ($array as $text) {
			$this->msg($text,$type);
		}
		
		return $this;
	}

	protected function redirect($msg,$type,$sticky,$redirect)
	{
		/* add another message to any that might already be on there */
		$this->redirect_messages[md5(trim($msg))] = ['msg' => trim($msg), 'type' => $type, 'sticky' => $sticky];

		/* store this in a session variable */
		$this->session->set_flashdata($this->msg_key, $this->redirect_messages);

		redirect($redirect);
	}

	protected function add2page($msg,$type,$sticky)
	{
		/* add to the current wallet messages */
		$current_msgs = $this->get_view_variable();

		/* add messages */
		$current_msgs[md5(trim($msg))] = ['msg' => trim($msg), 'type' => $type, 'sticky' => $sticky];

		/* put back in view variable */
		$this->set_view_variable($current_msgs);
	}

	protected function set_view_variable($messages)
	{
		/* get any flash messages in the session and add them to the view data */
		$this->load->vars([$this->view_variable => [
			'messages'       => $messages,
			'initial_pause'  => (($this->config['initial_pause']) ?? 3),
			'pause_for_each' => (($this->config['pause_for_each']) ?? 1000),
		]]);
	}

	protected function get_view_variable()
	{
		/* get the current messages */
		$wallet_messages = $this->load->get_var($this->view_variable);

		/* we only need the messages */
		return (array)$wallet_messages['messages'];
	}

} /* end class */
