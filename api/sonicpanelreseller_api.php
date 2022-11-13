<?php
/**
 * SonicPanel API.
 *
 * @copyright Gigaion LLC
 * @license MIT License
 * @see license.md (MIT License)
 */
class SonicpanelresellerApi
{
    private $adminusername;
    private $adminapikey;
	private $ipaddress;
	private $usessl;
	private $useproxy;
	
    /**
     * Initializes the class.
     *
     * @param mixed $password
     * @param mixed $ipaddress
     * @param mixed $usessl
     */
    public function __construct($adminapikey, $ipaddress, $usessl, $adminusername, $useproxy)
    {
		$this->adminapikey = $adminapikey;
        $this->ipaddress = $ipaddress;
        $this->usessl = $usessl;
		$this->adminusername = $adminusername;
		$this->useproxy = $useproxy;
    }

    /**
     * Return a string containing the last error for the current session.
     *
     * @param string $command the SonicPanel API command to call
     * @param array $params the parameters to include in the API request
     * @return mixed string|Array the curl error message or an array representing the API response
     */
    private function apiRequest($command, array $params)
    {
		$curl = curl_init();
		$params['cmd'] = $command;
		$params['ip'] = $this->ipaddress;
		
		$params['owner'] = $this->adminusername;
		$params['key'] = $this->adminapikey;
		$params = http_build_query($params);
		
		//Use http. As hostname is not being used for API request. (IP does not support https on sonicpanel. Only hostname)
		$url = 'http://';
		$port = '2086';
		
		$url .= $this->ipaddress . ':' . $port . '/api/sonic_api.php';
		
		$proxyEnabled = false;
		if($this->useproxy == 'true' && $proxyEnabled) {
			$urlproxy = 'https://example.proxy.lan/proxy.php';
			
			curl_setopt($curl, CURLOPT_HTTPHEADER, array(
				'Proxy-Auth: CENSORED-AUTH-KEY',
				'Proxy-Target-URL: '.$url,
				//'Proxy-Debug: 1'
			));
			curl_setopt($curl, CURLOPT_URL, $urlproxy);
		}
		else {
			curl_setopt($curl, CURLOPT_URL, $url);
		}
		
		
		if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
		   curl_setopt($curl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
		}
		curl_setopt($curl, CURLAUTH_BASIC, CURLAUTH_ANY);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
		$curl_output = curl_exec($curl);
		$error = curl_error($curl);
		curl_close($curl);
		
		if (empty($error)) {
			return $curl_output;
		}
		
		return $error;
    }


    /**
     * Get packages from root/reseller account.
     *
     * @param string $username the account's username to suspend
     * @return array an array representing the status of the operation
     */
    public function getPackagesList()
    {
        $status = false;
		
        $response = $this->apiRequest('reseller_packs', array());
		$json = json_decode($response, true);
		$return = $response;
		
        if ($return != '') {
            $status = true;
        }

        return [
            'status' => $status,
            'response' => $return,
        ];
    }

    /**
     * Creates a user account.
     *
     * @param array $params an array of parameters
     * @return array an array representing the status of the operation
     */
    public function createRadio(array $params)
    {
        $status = false;
        $parameters = [];
        $parameters['esend'] = 'yes'; // always send confirmation email to client
		
        $parameters['rad_username'] = $params['radiousername'];
		
        if (isset($params['radiopassword'])) {
            $parameters['panel_pass'] = $params['radiopassword'];
        }
        if (isset($params['radioemail'])) {
            $parameters['client_email'] = $params['radioemail'];
        }
        if (isset($params['package'])) {
            $parameters['package'] = $params['package'];
        }
		
        $response = $this->apiRequest('create_reseller', $parameters);
		$json = json_decode($response, true);
		$return = $json['result'];
		
		if(isset($json['username'])) {
			if($json['username'] == $parameters['rad_username']) {
				if ($return == 'complete') {
					$status = true;
				}
			}
		}
		
        return [
            'status' => $status,
            'response' => $return,
        ];
    }

    /**
     * Change a user account password.
     *
     * @param string $radiousername the account's username to change password
     * @return array an array representing the status of the operation
     */
    public function changePassword($radiousername, $newradiopassword)
    {
        $status = false;
		
        $response = $this->apiRequest('changepass', [
            'rad_username' => $radiousername,
            'password' => $newradiopassword,
        ]);
		$json = json_decode($response, true);
		$return = $json['result'];
		
        if ($return == 'complete') {
            $status = true;
        }

        return [
            'status' => $status,
            'response' => $return,
        ];
    }

    /**
     * Suspends a user account.
     *
     * @param string $radiousername the account's username to suspend
     * @return array an array representing the status of the operation
     */
    public function suspendRadio($radiousername)
    {
        $status = false;
		
        $response = $this->apiRequest('suspend_reseller', [
            'rad_username' => $radiousername,
        ]);
		$json = json_decode($response, true);
		$return = $json['result'];
		
        if ($return == 'complete') {
            $status = true;
        }

        return [
            'status' => $status,
            'response' => $return,
        ];
    }

    /**
     * Un-suspends a user account.
     *
     * @param string $radiousername the account's username to un-suspend
     * @return array an array representing the status of the operation
     */
    public function unSuspendRadio($radiousername)
    {
        $status = false;

        $response = $this->apiRequest('unsuspend_reseller', [
            'rad_username' => $radiousername,
        ]);
		$json = json_decode($response, true);
		$return = $json['result'];
		
        if ($return == 'complete') {
            $status = true;
        }

        return [
            'status' => $status,
            'response' => $return,
        ];
    }

    /**
     * Terminates a user account.
     *
     * @param string $radiousername the account's username to terminate
     * @return array an array representing the status of the operation
     */
    public function terminateRadio($radiousername)
    {
        $status = false;

        $response = $this->apiRequest('terminate_reseller', [
            'rad_username' => $radiousername,
        ]);
		$json = json_decode($response, true);
		$return = $json['result'];
		
        if ($return == 'complete') {
            $status = true;
        }

        return [
            'status' => $status,
            'response' => $return,
        ];
    }

    /**
     * Change a user account package.
     *
     * @param string $radiousername the account's username
     * @param string $package the account's package to change
     * @return array an array representing the status of the operation
     */
    public function changePackage($radiousername, $package)
    {
        $status = false;

        $response = $this->apiRequest('change_reseller', [
            'rad_username' => $radiousername,
            'pack' => $package,
        ]);
		$json = json_decode($response, true);
		$return = $json['result'];
		
        if ($return == 'complete') {
            $status = true;
        }

        return [
            'status' => $status,
            'response' => $return,
        ];
    }
	
}


?>
