<?php 
defined('BASEPATH') OR exit('No direct script access allowed');

class Ms_entra_api 
{
	private $ch;
	private $client_redis_key;
	private $redis_host;
	private $redis_port;
	private $redis_timeout;  
	private $redis_password;
	private $auth_host;
	private $api_host;
	private $scope;
	private $api_base_url;
	private $oauth_access_token;
	private $page_size;

	function __construct()
	{
		$CI =& get_instance();

		$this->client_redis_key = 'ms_entra_';		
		$this->redis_host       = $CI->config->item('redis_host');
		$this->redis_port       = $CI->config->item('redis_port');
		$this->redis_password   = $CI->config->item('redis_password');
		$this->redis_timeout    = $CI->config->item('redis_timeout');
		$this->auth_host        = 'login.microsoftonline.com';
		$this->api_host         = 'graph.microsoft.com';
		$this->scope            = 'https://'.$this->api_host.'/.default';
		$this->api_base_url     = 'https://'.$this->api_host.'/v1.0';
		$this->page_size        = $CI->config->item('ms_entra_page_size') ?? 999;  // Must be between 1 and 999 inclusive
	}

	public function redis_info($client, $field = NULL, $action = 'GET', $data = NULL)
	{
		$client_info    = client_redis_info($client);
		$client_key     = $this->client_redis_key.$client;

		$redis = new Redis();
		$redis->connect($client_info['redis_host'], $client_info['redis_port'], $this->redis_timeout);
		$redis->auth($client_info['redis_password']);

		if ($action === 'SET')
		{
			$check = $redis->hMSet($client_key, $data);
		}
		else
		{
			if (is_null($field))
			{
				$check = $redis->hGetAll($client_key);
			}
			else
			{
				$check = $redis->hGet($client_key, $field);
			}
		}     

		$redis->close();

		if (empty($check))
		{
			$check = NULL;
		}

		return $check;		
	}

	public function create_ms_entra_redis_key($client, $data = NULL)
	{
		$client_info    = client_redis_info($client);
		$client_key     = $this->client_redis_key.$client;

		$redis = new Redis();
		$redis->connect($client_info['redis_host'], $client_info['redis_port'], $this->redis_timeout);
		$redis->auth($client_info['redis_password']);

		$check = $redis->hMSet($client_key, [
			'tenant_id'     => $data['tenant_id'],
			'client_id'     => $data['client_id'],
			'client_secret' => $data['client_secret'],
			'tested'        => '0',
			'request_sent'  => '0',
			'enabled'       => '0',
			'terms_agreed'  => '0'
		]);

		$redis->close();

		return $check;		
	}

	public function change_api_activation_status($client, $requested, $status)
	{
		$set_activation = ($status) ? 1 : 0;
		$check          = FALSE;

		#set soc redis keys
		$redis = new Redis();
		$redis->connect($this->redis_host, $this->redis_port, $this->redis_timeout);
		$redis->auth($this->redis_password);

		$check = $redis->hSet($client.'_information', 'ms_entra_enabled', $set_activation);

		$redis->close();

		# set client redis keys
		if (is_int($check))
		{
			$status_data = array(
				'enabled'       => $set_activation,
				'request_sent'  => $set_activation,
				'request_user'  => $requested,
				'terms_agreed'  => $set_activation
			);

			$config_data = array(
				'ms_entra_enabled' => $set_activation
			);

			if ($this->redis_info($client, NULL, 'SET', $status_data))
			{
				if ($this->client_config($client, NULL, 'SET', $config_data))
				{
					return TRUE;
				}
			}
		}

		return FALSE;
	}

	private function client_config($client, $field = NULL, $action = 'GET', $data = NULL)
	{
		$client_info    = client_redis_info($client);
		$client_key     = $client.'_configurations';

		$redis = new Redis();
		$redis->connect($client_info['redis_host'], $client_info['redis_port'], $this->redis_timeout);
		$redis->auth($client_info['redis_password']);

		if ($action === 'SET')
		{
			$check = $redis->hMSet($client_key, $data);
		}
		else
		{
			if (is_null($field))
			{
				$check = $redis->hGetAll($client_key);
			}
			else
			{
				$check = $redis->hGet($client_key, $field);
			}
		}   

		$redis->close();

		if (empty($check))
		{
			$check = NULL;
		}

		return $check;		
	}

	/**
	 * Required Permissions
	 *
	 * API / Permissions name: User.Read.All
	 * Type: Application
	 * Admin consent required: Yes
	 * 
	 */
	public function get_users_by_filter($client, $search_params = NULL)
	{
		$url        = $this->api_base_url.'/users';
		$response   = $this->get_oauth_access_token($client);

		if (!$response['success'])
		{
			return array(
				'success'   => FALSE,
				'response'  => $response['response']
			);
		}

		$header_fields = array(
			'Accept: application/json',
			'Authorization: Bearer '.$response['access_token']
		);

		$query_params = array();

		if (!is_null($search_params))
		{
			switch ($search_params['type'])
			{
				case 'disabled':
					$query_params['$filter']    = 'accountEnabled eq false';
					$query_params['$top']       = $this->page_size;
					break;
				case 'enabled':
					$query_params['$filter']    = 'accountEnabled eq true';
					$query_params['$top']       = $this->page_size;
					break;
				case 'email_equals':
					$query_params['$filter']    = "mail eq '".$search_params['term']."'";
					break;
				case 'details':
					$query_params['$filter']    = "id eq '".$search_params['term']."'";
					$query_params['$select']    = 'businessPhones,displayName,givenName,jobTitle,mail,mobilePhone,officeLocation,preferredLanguage,surname,userPrincipalName,id,accountEnabled,passwordProfile';
					break;
				case 'name_starts_with':
					$query_params['$filter']    = "startswith(displayName,'".$search_params['term']."')";
					break;
				case 'email_contains':
				case 'list':
				case 'name_contains':
				default:
					$query_params['$orderby']   = 'displayName';
					$query_params['$top']       = $this->page_size;
			}
		}
		else
		{
			$query_params['$orderby']   = 'displayName';
			$query_params['$top']       = $this->page_size;
		}

		$response2 = $this->call_api('GET', $url.'?'.http_build_query($query_params), $header_fields);

		if ($response2['result'] !== FALSE)
		{
			if ($response2['http_code'] === 200)
			{
				return array(
					'success'   => TRUE,
					'response'  => $response2['result']
				);
			}
			else
			{
				return array(
					'success'   => FALSE,
					'response'  => $response2['result']
				);
			}
		}
		else
		{
			return array(
				'success'   => FALSE,
				'response'  => array(
					'error'    => array(
						'code'      => $response2['errno'],
						'message'   => $response2['error']
					)
				)
			);
		}
	}

	/**
	 * Required Permissions
	 *
	 * Action: enable_acct, disable_acct
	 * API / Permissions name: User.EnableDisableAccount.All + User.Read.All
	 * Type: Application
	 * Admin consent required: Yes
	 * 
	 * Action: force_change_pw, force_change_pw_with_mfa, reset_pw_profile
	 * API / Permissions name: User-PasswordProfile.ReadWrite.All
	 * Type: Application
	 * Admin consent required: Yes
	 * 
	 */
	public function update_user($client, $user_id, $action)
	{
		$url        = $this->api_base_url.'/users/'.$user_id;
		$response   = $this->get_oauth_access_token($client);

		if (!$response['success'])
		{
			return array(
				'success'   => FALSE,
				'response'  => $response['response']
			);
		}

		$header_fields = array(
			'Content-Type: application/json',
			'Accept: application/json',
			'Authorization: Bearer '.$response['access_token']
		);

		$post_fields = new stdClass();

		switch ($action)
		{
			case 'disable_acct':
				$post_fields->accountEnabled = false;
				break;
			case 'enable_acct':
				$post_fields->accountEnabled = true;
				break;
			case 'force_change_pw':
				$pw_profile = new stdClass();
				$pw_profile->forceChangePasswordNextSignIn = true;

				$post_fields->passwordProfile = $pw_profile;
				break;
			case 'force_change_pw_with_mfa':
				$pw_profile = new stdClass();
				$pw_profile->forceChangePasswordNextSignInWithMfa = true;

				$post_fields->passwordProfile = $pw_profile;
				break;
			case 'reset_pw_profile':
				$pw_profile = new stdClass();
				$pw_profile->forceChangePasswordNextSignIn = false;
				$pw_profile->forceChangePasswordNextSignInWithMfa = false;

				$post_fields->passwordProfile = $pw_profile;
				break;
		}

		$response2 = $this->call_api('PATCH', $url, $header_fields, json_encode($post_fields));

		if ($response2['result'] !== FALSE)
		{
			if ($response2['http_code'] === 204)
			{
				return array(
					'success'   => TRUE,
					'response'  => $response2['result']
				);
			}
			else
			{
				return array(
					'success'   => FALSE,
					'response'  => $response2['result']
				);
			}
		}
		else
		{
			return array(
				'success'   => FALSE,
				'response'  => array(
					'error'    => array(
						'code'      => $response2['errno'],
						'message'   => $response2['error']
					)
				)
			);
		}
	}

	/**
	 * Required Permissions
	 *
	 * API / Permissions name: User.RevokeSessions.All
	 * Type: Application
	 * Admin consent required: Yes
	 * 
	 */
	public function revoke_sign_in_sessions($client, $user_id)
	{
		$url        = $this->api_base_url.'/users/'.$user_id.'/revokeSignInSessions';
		$response   = $this->get_oauth_access_token($client);

		if (!$response['success'])
		{
			return array(
				'success'   => FALSE,
				'response'  => $response['response']
			);
		}

		$header_fields = array(
			'Content-Type: application/json',
			'Accept: application/json',
			'Authorization: Bearer '.$response['access_token']
		);

		$response2 = $this->call_api('POST', $url, $header_fields);

		if ($response2['result'] !== FALSE)
		{
			if ($response2['http_code'] === 200)
			{
				return array(
					'success'   => TRUE,
					'response'  => $response2['result']
				);
			}
			else
			{
				return array(
					'success'   => FALSE,
					'response'  => $response2['result']
				);
			}
		}
		else
		{
			return array(
				'success'   => FALSE,
				'response'  => array(
					'error'    => array(
						'code'      => $response2['errno'],
						'message'   => $response2['error']
					)
				)
			);
		}
	}

	/**
	 * Required Permissions
	 *
	 * API / Permissions name: UserAuthenticationMethod.ReadWrite.All
	 * Type: Application
	 * Admin consent required: Yes
	 * 
	 */
	private function get_authentication_methods($client, $user_id)
	{
		$url        = $this->api_base_url.'/users/'.$user_id.'/authentication/methods';
		$response   = $this->get_oauth_access_token($client);

		if (!$response['success'])
		{
			return array(
				'success'   => FALSE,
				'response'  => $response['response']
			);
		}

		$header_fields = array(
			'Accept: application/json',
			'Authorization: Bearer '.$response['access_token']
		);

		$response2 = $this->call_api('GET', $url, $header_fields);

		if ($response2['result'] !== FALSE)
		{
			if ($response2['http_code'] === 200)
			{
				return array(
					'success'   => TRUE,
					'response'  => $response2['result']
				);
			}
			else
			{
				return array(
					'success'   => FALSE,
					'response'  => $response2['result']
				);
			}
		}
		else
		{
			return array(
				'success'   => FALSE,
				'response'  => array(
					'error'    => array(
						'code'      => $response2['errno'],
						'message'   => $response2['error']
					)
				)
			);
		}
	}

	/**
	 * Required Permissions
	 *
	 * API / Permissions name: UserAuthenticationMethod.ReadWrite.All
	 * Type: Application
	 * Admin consent required: Yes
	 * 
	 */
	public function clear_authentication_methods($client, $user_id)
	{
		$response = $this->get_authentication_methods($client, $user_id);

		if (!$response['success'] || empty($response['response']['value']))
		{
			return array(
				'success'   => FALSE,
				'response'  => $response['response']
			);
		}

		$authentication_methods = 0;
		$phone_methods_pass     = 0;
		$phone_methods_fail     = 0;
		$ms_auth_methods_pass   = 0;
		$ms_auth_methods_fail   = 0;

		foreach ($response['response']['value'] as $method)
		{
			switch ($method['@odata.type'])
			{
				case '#microsoft.graph.phoneAuthenticationMethod':
					$authentication_methods++;

					$response2 = $this->remove_phone_method($client, $user_id, $method['id']);

					if ($response2['success'])
					{
						$phone_methods_pass++;
					}
					else
					{
						$phone_methods_fail++;
					}

					break;
				case '#microsoft.graph.microsoftAuthenticatorAuthenticationMethod':
					$authentication_methods++;

					$response2 = $this->remove_ms_authenticator_method($client, $user_id, $method['id']);

					if ($response2['success'])
					{
						$ms_auth_methods_pass++;
					}
					else
					{
						$ms_auth_methods_fail++;
					}

					break;
			}
		}

		if ($authentication_methods === $phone_methods_pass + $ms_auth_methods_pass)
		{
			$ret_value = TRUE;

			if (!$phone_methods_pass && !$ms_auth_methods_pass)
			{
				$message = 'No authentication methods were removed.';
			}
			else if (!$phone_methods_pass && $ms_auth_methods_pass)
			{
				$message = 'Authentication methods removed: Microsoft Authenticator ('.$ms_auth_methods_pass.').';
			}
			else if ($phone_methods_pass && !$ms_auth_methods_pass)
			{
				$message = 'Authentication methods removed: Phone ('.$phone_methods_pass.').';
			}
			else if ($phone_methods_pass && $ms_auth_methods_pass)
			{
				$message = 'Authentication methods removed: Phone ('.$phone_methods_pass.'), Microsoft Authenticator ('.$ms_auth_methods_pass.').';
			}
		}
		else
		{
			$ret_value = FALSE;

			if (!$phone_methods_fail && $ms_auth_methods_fail)
			{
				$message = 'Authentication methods not removed: Microsoft Authenticator ('.$ms_auth_methods_fail.').';
			}
			else if ($phone_methods_fail && !$ms_auth_methods_fail)
			{
				$message = 'Authentication methods not removed: Phone ('.$phone_methods_fail.').';
			}
			else if ($phone_methods_fail && $ms_auth_methods_fail)
			{
				$message = 'Authentication methods not removed: Phone ('.$phone_methods_fail.'), Microsoft Authenticator ('.$ms_auth_methods_fail.').';
			}
		}

		if ($ret_value)
		{
			return array(
				'success'   => TRUE,
				'response'  => array(
					'message'   => $message
				)
			);
		}
		else
		{
			return array(
				'success'   => FALSE,
				'response'  => array(
					'error'    => array(
						'code'      => 'clear_authentication_methods()',
						'message'   => $message
					)
				)
			);
		}
	}

	/**
	 * Required Permissions
	 *
	 * API / Permissions name: UserAuthenticationMethod.ReadWrite.All
	 * Type: Application
	 * Admin consent required: Yes
	 * 
	 */
	private function remove_phone_method($client, $user_id, $phone_num_type_id)
	{
		// The IDs of the different phone number types are globally the same across Microsoft Entra ID as follows:
		// b6332ec1-7057-4abe-9331-3d72feddfe41 for alternateMobile phone type
		// e37fc753-ff3b-4958-9484-eaa9425c82bc for office phone type
		// 3179e48a-750b-4051-897c-87b9720928f7 for mobile phone type

		$url        = $this->api_base_url.'/users/'.$user_id.'/authentication/phoneMethods/'.$phone_num_type_id;
		$response   = $this->get_oauth_access_token($client);

		if (!$response['success'])
		{
			return array(
				'success'   => FALSE,
				'response'  => $response['response']
			);
		}

		$header_fields = array(
			'Accept: application/json',
			'Authorization: Bearer '.$response['access_token']
		);

		$response2 = $this->call_api('DELETE', $url, $header_fields);

		if ($response2['result'] !== FALSE)
		{
			if ($response2['http_code'] === 204)
			{
				return array(
					'success'   => TRUE,
					'response'  => $response2['result']
				);
			}
			else
			{
				return array(
					'success'   => FALSE,
					'response'  => $response2['result']
				);
			}
		}
		else
		{
			return array(
				'success'   => FALSE,
				'response'  => array(
					'error'    => array(
						'code'      => $response2['errno'],
						'message'   => $response2['error']
					)
				)
			);
		}
	}

	/**
	 * Required Permissions
	 *
	 * API / Permissions name: UserAuthenticationMethod.ReadWrite.All
	 * Type: Application
	 * Admin consent required: Yes
	 * 
	 */
	private function remove_ms_authenticator_method($client, $user_id, $ms_authenticator_method_id)
	{
		$url        = $this->api_base_url.'/users/'.$user_id.'/authentication/microsoftAuthenticatorMethods/'.$ms_authenticator_method_id;
		$response   = $this->get_oauth_access_token($client);

		if (!$response['success'])
		{
			return array(
				'success'   => FALSE,
				'response'  => $response['response']
			);
		}

		$header_fields = array(
			'Accept: application/json',
			'Authorization: Bearer '.$response['access_token']
		);

		$response2 = $this->call_api('DELETE', $url, $header_fields);

		if ($response2['result'] !== FALSE)
		{
			if ($response2['http_code'] === 204)
			{
				return array(
					'success'   => TRUE,
					'response'  => $response2['result']
				);
			}
			else
			{
				return array(
					'success'   => FALSE,
					'response'  => $response2['result']
				);
			}
		}
		else
		{
			return array(
				'success'   => FALSE,
				'response'  => array(
					'error'    => array(
						'code'      => $response2['errno'],
						'message'   => $response2['error']
					)
				)
			);
		}
	}

	/**
	 * Required Permissions
	 *
	 * API / Permissions name: UserAuthenticationMethod.ReadWrite.All
	 * Type: Application
	 * Admin consent required: Yes
	 * 
	 */
	public function add_phone_method($client, $user_id, $phone_num, $phone_num_type)
	{
		$url        = $this->api_base_url.'/users/'.$user_id.'/authentication/phoneMethods';
		$response   = $this->get_oauth_access_token($client);

		if (!$response['success'])
		{
			return array(
				'success'   => FALSE,
				'response'  => $response['response']
			);
		}

		$header_fields = array(
			'Content-Type: application/json',
			'Accept: application/json',
			'Authorization: Bearer '.$response['access_token']
		);

		$post_fields = new stdClass();
		$post_fields->phoneNumber   = $phone_num;
		$post_fields->phoneType     = $phone_num_type;

		$response2 = $this->call_api('POST', $url, $header_fields, json_encode($post_fields));

		if ($response2['result'] !== FALSE)
		{
			if ($response2['http_code'] === 201)
			{
				return array(
					'success'   => TRUE,
					'response'  => $response2['result']
				);
			}
			else
			{
				return array(
					'success'   => FALSE,
					'response'  => $response2['result']
				);
			}
		}
		else
		{
			return array(
				'success'   => FALSE,
				'response'  => array(
					'error'    => array(
						'code'      => $response2['errno'],
						'message'   => $response2['error']
					)
				)
			);
		}
	}

	private function get_oauth_access_token($client)
	{
		$ms_entra_info = $this->redis_info($client);

		if (isset($ms_entra_info['oauth_access_token']))
		{
			$this->oauth_access_token = json_decode($ms_entra_info['oauth_access_token']);
		}

		if ($this->is_access_token_expired())
		{
			$url = 'https://'.$this->auth_host.'/'.$ms_entra_info['tenant_id'].'/oauth2/v2.0/token';

			$header_fields = array(
				'Content-Type: application/x-www-form-urlencoded',
				'Accept: application/json'
			);

			$post_fields = array(
				'client_id'     => $ms_entra_info['client_id'],
				'client_secret' => $ms_entra_info['client_secret'],
				'scope'         => $this->scope,
				'grant_type'    => 'client_credentials'
			);

			$response = $this->call_api('POST', $url, $header_fields, http_build_query($post_fields));

			if ($response['result'] !== FALSE)
			{
				if ($response['http_code'] === 200)
				{
					$this->oauth_access_token               = $response['result'];
					$this->oauth_access_token['created_at'] = time();
					$this->oauth_access_token               = json_encode($this->oauth_access_token);

					$this->redis_info($client, NULL, 'SET', array('oauth_access_token' => $this->oauth_access_token));

					$this->oauth_access_token = json_decode($this->oauth_access_token);
				}
				else
				{
					return array(
						'success'   => FALSE,
						'response'  => $response['result']
					);
				}
			}
			else
			{
				return array(
					'success'   => FALSE,
					'response'  => array(
						'error'    => array(
							'code'      => $response['errno'],
							'message'   => $response['error']
						)
					)
				);
			}
		}

		return array(
			'success'       => TRUE,
			'access_token'  => $this->oauth_access_token->access_token
		);
	}

	private function is_access_token_expired()
	{
		if (!$this->oauth_access_token)
		{
			return TRUE;
		}

		// If the OAuth access token does not have an 'expires_in' property, then it's considered expired
		if (!isset($this->oauth_access_token->expires_in))
		{
			return TRUE;
		}

		$created_at = 0;

		if (isset($this->oauth_access_token->created_at))
		{
			$created_at = $this->oauth_access_token->created_at;
		}

		// If the OAuth access token is set to expire in the next 30 seconds.
		return ($created_at + $this->oauth_access_token->expires_in - 30) < time();
	}

	private function call_api($method, $url, $header_fields, $post_fields = NULL)
	{
		$this->ch = curl_init();

		switch ($method)
		{
			case 'POST':
				curl_setopt($this->ch, CURLOPT_POST, true);

				if (isset($post_fields))
				{
					curl_setopt($this->ch, CURLOPT_POSTFIELDS, $post_fields);
				}

				break;
			case 'PUT':
				curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'PUT');

				if (isset($post_fields))
				{
					curl_setopt($this->ch, CURLOPT_POSTFIELDS, $post_fields);
				}

				break;
			case 'PATCH':
				curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'PATCH');

				if (isset($post_fields))
				{
					curl_setopt($this->ch, CURLOPT_POSTFIELDS, $post_fields);
				}

				break;
			case 'DELETE':
				curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
				break;
		}

		if (is_array($header_fields))
		{
			curl_setopt($this->ch, CURLOPT_HTTPHEADER, $header_fields);
		}

		curl_setopt($this->ch, CURLOPT_URL, $url);
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
		//curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, false);
		//curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);

		curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, 5);
		//curl_setopt($this->ch, CURLOPT_TIMEOUT, 10);

		if (($response['result'] = curl_exec($this->ch)) !== FALSE)
		{
			// Make sure the size of the response is non-zero prior to json_decode()
			if (curl_getinfo($this->ch, CURLINFO_SIZE_DOWNLOAD_T))
			{
				$response['result'] = json_decode($response['result'], TRUE);
			}

			$response['http_code'] = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
		}
		else
		{
			$response['errno'] 	= curl_errno($this->ch);
			$response['error'] 	= curl_error($this->ch);
		}

		curl_close($this->ch);

		return $response;
	}
}