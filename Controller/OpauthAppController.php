<?php
/**
 * CakePHP plugin for Opauth
 *
 * @copyright    Copyright © 2012-2013 U-Zyn Chua (http://uzyn.com)
 * @link         http://opauth.org
 * @license      MIT License
 */
class OpauthAppController extends AppController
{
    public $uses = array();

    private $_config = null;

    /**
     * Opauth instance
     */
    public $Opauth;

    /**
     * {@inheritDoc}
     */
    public function __construct($request = null, $response = null)
    {
        parent::__construct($request, $response);

		$this->autoRender = false;

		$this->_getConfig();
    }

    /**
     * Catch all for Opauth
     */
    public function index()
    {
        $this->_loadOpauth();
        $this->Opauth->run();

        return;
    }

    /**
     * Receives auth response and does validation
     */
    public function callback()
    {
        $response = null;

        /**
        * Fetch auth response, based on transport configuration for callback
        */
        switch ($this->_config['callback_transport']) {
            case 'session':
                if (!session_id()) {
                    session_start();
                }

                if (isset($_SESSION['opauth'])) {
                    $response = $_SESSION['opauth'];
                    unset($_SESSION['opauth']);
                }
                break;
            case 'post':
                $response = json_decode((base64_decode($_POST['opauth'])), true);
                break;
            case 'get':
                $response = json_decode((base64_decode($_GET['opauth'])), true);
                break;
            default:
                throw new exception("Unsupported callback_transport: " . $this->_config['callback_transport']);
        }

        /**
         * Check if it's an error callback
         */
        if (isset($response) && is_array($response) && array_key_exists('error', $response)) {
            // Error
            $response['validated'] = false;
        }

        /**
         * Auth response validation
         *
         * To validate that the auth response received is unaltered, especially auth response that
         * is sent through GET or POST.
         */
        else {
            $this->_loadOpauth();

            if (empty($response['auth']) || empty($response['timestamp']) || empty($response['signature']) || empty($response['auth']['provider']) || empty($response['auth']['uid'])) {
                $response['error'] = array(
                    'provider' => $response['auth']['provider'],
                    'code' => 'invalid_auth_missing_components',
                    'message' => 'Invalid auth response: Missing key auth response components.'
                );
                $response['validated'] = false;
            } elseif (!($this->Opauth->validate(sha1(print_r($response['auth'], true)), $response['timestamp'], $response['signature'], $reason))) {
                $response['error'] = array(
                    'provider' => $response['auth']['provider'],
                    'code' => 'invalid_auth_failed_validation',
                    'message' => 'Invalid auth response: '.$reason
                );
                $response['validated'] = false;
            } else {
                $response['validated'] = true;
            }
        }

        /**
         * Redirect user to the Opauth.cakephp_plugin_complete_url
		 *
		 * The validated response data is available as POST data, retrievable at $this->data at your app's controller.
         */
        $completeUrl = empty($this->_config['cakephp_plugin_complete_url']) ? Router::url('/opauth-complete') : $this->_config['cakephp_plugin_complete_url'];

        $CakeRequest = new CakeRequest($completeUrl);
        $CakeRequest->data = $response;

        $Dispatcher = new Dispatcher();
        $Dispatcher->dispatch($CakeRequest, new CakeResponse());
        exit();
    }

    /**
     * Instantiate Opauth
     *
     * @param array $config User configuration
     * @param boolean $run Whether Opauth should auto run after initialization.
     */
    protected function _loadOpauth($config = null, $run = false)
    {
		$this->_getConfig($config);
        $this->Opauth = new Opauth($this->_config, $run);
	}

	/**
	 * Load the app private configuration file as per http://book.cakephp.org/2.0/en/development/configuration.html#loading-configuration-files
	 *
	 * @param array $config				Optional config to set
	 */
	private function _getConfig($config = null)
	{
        if (is_null($config)) {

            Configure::config('private', new PhpReader(ROOT . DS . 'app' . DS . 'Config' . DS));
            Configure::load('private.php', 'private');

            $config = Configure::read('Opauth');
        }
        $this->_config = $config;
	}
}
