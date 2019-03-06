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
 * @uses # \session - CodeIgniter Session
 * @uses # \event - Orange Event
 * @uses # \load - CodeIgniter Loader
 *
 * @config sticky_types `['red','danger','warning','yellow']`
 * @config initial_pause `3`
 * @config pause_for_each `1000`
 *
 */
class Wallet
{
	/**
	 * Storage of redirect messages
	 *
	 * @var Array
	 */
	protected $redirect_messages = [];

	/**
	 * Session key for wallet messages
	 *
	 * @var String
	 */
	protected $msg_key = 'internal::wallet::msg';

	/**
	 * View variable to place the redirect messages
	 * This can then be used by the views javascript to display the messages
	 * or for further processing
	 *
	 * @var String
	 */
	protected $view_variable = 'wallet_messages';

	/**
	 * Servers HTTP Referer
	 *
	 * @var string
	 */
	protected $http_referer;

	/**
	 * Local reference of wallet configuration
	 *
	 * @var Array
	 */
	protected $config;

	/**
	 * Local reference of CodeIgniter Loader
	 *
	 * @var Object
	 */
	protected $load;

	/**
	 * Local reference of CodeIgniter Session
	 *
	 * @var Object
	 */
	protected $session;

	/**
	 * Local reference of Orange Event
	 *
	 * @var \Event
	 */
	protected $event;

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
		$this->load = &ci('load');

		$this->http_referer = ci('input')->server('HTTP_REFERER');
		$this->sticky_types = ($this->config['sticky_types']) ?? ['red','danger','warning','yellow'];

		/* set the view variable if any messages are available */
		$current_messages = $this->session->flashdata($this->msg_key);

		if (is_array($current_messages)) {
			$this->set_view_variable($current_messages);
		}

		log_message('info', 'Wallet Class Initialized');
	}

	/**
	 *
	 * Add a flash message to the current page as javascript variable
	 * Add a flash message as a session flash message and redirect
	 *
	 * @access public
	 *
	 * @param string $msg
	 * @param string $type yellow
	 * @param $redirect null, string, or true. if true http referring page will be used.
	 *
	 * @return \Wallet
	 *
	 * #### Example
	 * ```php
	 * ci('wallet')->msg('Oh No!','yellow');
	 * ci('wallet')->msg('oH No!','red','/folder/new');
	 * ```
	 */
	public function msg(string $msg = '', string $type = 'yellow', $redirect = null) : Wallet
	{
		/* is this type sticky? - use names not colors - colors support for legacy code */
		$sticky = in_array($type, $this->sticky_types);

		/* trigger a event incase they need to do something */
		$this->event->trigger('wallet.msg', $msg, $type, $sticky, $redirect);

		/* is this a redirect */
		if (is_string($redirect)) {
			$this->redirect($msg, $type, $sticky, $redirect);
		} elseif ($redirect === true) {
			$this->redirect($msg, $type, $sticky, $this->http_referer);
		} else {
			$this->add2page($msg, $type, $sticky);
		}

		return $this;
	}

	/**
	 *
	 * Add multiple messages at one time
	 *
	 * @access public
	 *
	 * @param array $array
	 * @param string $type blue
	 *
	 * @return \Wallet
	 *
	 * #### Example
	 * ```php
	 * ci('wallet')->msgs(['Whoops!','Defcon 1'=>'red','Info']);
	 * ```
	 */
	public function msgs(array $array, string $type='yellow') : Wallet
	{
		foreach ($array as $a=>$b) {
			if (is_numeric($a)) {
				$this->msg($b, $type);
			} else {
				$this->msg($a, $b);
			}
		}

		return $this;
	}

	/**
	 *
	 * Add a message and redirect
	 *
	 * @access protected
	 *
	 * @param $msg
	 * @param $type
	 * @param $sticky
	 * @param $redirect
	 *
	 * @return void
	 *
	 */
	protected function redirect(string $msg, string $type, bool $sticky, string $redirect) : void
	{
		/* add another message to any that might already be on there */
		$this->redirect_messages[md5(trim($msg))] = ['msg' => trim($msg), 'type' => $type, 'sticky' => $sticky];

		/* store this in a session variable */
		$this->session->set_flashdata($this->msg_key, $this->redirect_messages);

		redirect($redirect);
	}

	/**
	 *
	 * Add message to the current pages view javascript variable
	 *
	 * @access protected
	 *
	 * @param string $msg
	 * @param string $type
	 * @param bool $sticky
	 *
	 * @return \Wallet
	 *
	 */
	protected function add2page(string $msg, string $type, bool $sticky) : Wallet
	{
		/* add to the current wallet messages */
		$current_msgs = $this->get_view_variable();

		/* add messages */
		$current_msgs[md5(trim($msg))] = ['msg' => trim($msg), 'type' => $type, 'sticky' => $sticky];

		/* put back in view variable */
		$this->set_view_variable($current_msgs);

		return $this;
	}

	/**
	 *
	 * Get the page view variable contents
	 *
	 * @access protected
	 *
	 * @return Array
	 *
	 */
	protected function get_view_variable() : array
	{
		/* get the current messages */
		$wallet_messages = $this->load->get_var($this->view_variable);

		/* we only need the messages */
		return (array)$wallet_messages['messages'];
	}

	/**
	 *
	 * Set the page view variable
	 *
	 * @access protected
	 *
	 * @param $messages
	 *
	 * @return \Wallet
	 *
	 */
	protected function set_view_variable(array $messages) : Wallet
	{
		/* get any flash messages in the session and add them to the view data */
		$this->load->vars([$this->view_variable => [
			'messages'       => $messages,
			'initial_pause'  => (($this->config['initial_pause']) ?? 3),
			'pause_for_each' => (($this->config['pause_for_each']) ?? 1000),
		]]);

		return $this;
	}
} /* end class */
