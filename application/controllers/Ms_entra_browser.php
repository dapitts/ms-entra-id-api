<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Ms_entra_browser extends CI_Controller 
{
	private $redis_host;
	private $redis_port;
	private $redis_timeout;  
	private $redis_password;

	public function __construct()
	{
		parent::__construct();

		if (!$this->tank_auth->is_logged_in()) 
		{	
			if ($this->input->is_ajax_request()) 
			{
				redirect('/auth/ajax_logged_out_response');
			} 
			else 
			{
				redirect('/auth/login');
			}
		}

		$this->redis_host       = $this->config->item('redis_host');
		$this->redis_port       = $this->config->item('redis_port');
		$this->redis_password   = $this->config->item('redis_password');
		$this->redis_timeout    = $this->config->item('redis_timeout');

		$this->load->library('ms_entra_api');
	}

	public function index()
	{
		# Page Data
		$data['clients']            = $this->ms_entra_enabled_customers();
		$data['search_types']       = $this->search_type_dropdown();
		$data['set_search_type']    = '';

		# Page View
		$this->load->view('assets/header');
		$this->load->view('ms-entra-browser/start', $data);
		$this->load->view('assets/footer');
	}

	public function search()
	{
		$this->form_validation->set_rules('client_code', 'Client Code', 'trim|required');
		$this->form_validation->set_rules('entra_search_type', 'Search Type', 'trim|required');
		$this->form_validation->set_rules('entra_search_value', 'Search Term', 'trim');

		if ($this->form_validation->run()) 
		{
			$asset          = client_by_code($this->input->post('client_code'));
			$search_type    = $this->input->post('entra_search_type');
			$search_term    = $this->input->post('entra_search_value');

			$search_params = array(
				'type'  => $search_type,
				'term'  => $search_term
			);

			$response = $this->ms_entra_api->get_users_by_filter($asset['seed_name'], $search_params);

			if ($response['success'])
			{
				$results = [];

				foreach ($response['response']['value'] as $result)
				{
					if ($search_type === 'name_contains')
					{
						if (stripos($result['displayName'], $search_term) === FALSE)
						{
							continue;
						}
					}
					else if ($search_type === 'email_contains')
					{
						if (stripos($result['mail'], $search_term) === FALSE)
						{
							continue;
						}
					}

					$results[] = array(
						'display_name'  => $result['displayName'],
						'job_title'     => $result['jobTitle'] ?? 'N/A',
						'id'            => $result['id'],
						'client_code'   => $asset['code']
					);
				}

				$result_count = count($results);

				$response = array(
					'success'       => true,
					'results'       => $results,
					'result_count'  => $result_count
				);
			}
			else
			{
				$message = $this->get_error_message($response['response']);

				$response = array(
					'success'       => false,
					'message'       => $message,
					'csrf_name'     => $this->security->get_csrf_token_name(),
					'csrf_value'    => $this->security->get_csrf_hash()
				);
			}
		}
		else
		{
			$response = array(
				'success'       => false,
				'message'       => validation_errors(),
				'csrf_name'     => $this->security->get_csrf_token_name(),
				'csrf_value'    => $this->security->get_csrf_hash()
			);
		}

		echo json_encode($response);
	}

	public function get_details()
	{
		$client_code    = $this->uri->segment(3);
		$user_id        = $this->uri->segment(4);

		$asset = client_by_code($client_code);

		$search_params = array(
			'type'  => 'details',
			'term'  => $user_id
		);

		$response = $this->ms_entra_api->get_users_by_filter($asset['seed_name'], $search_params);

		if ($response['success'])
		{
			foreach ($response['response']['value'] as $result)
			{
				$results = $result;

				$details = array(
					'business_phone'        => !empty($result['businessPhones']) ? $result['businessPhones'][0] : false,
					'display_name'          => $result['displayName'] ?? false,
					'given_name'            => $result['givenName'] ?? false,
					'job_title'             => $result['jobTitle'] ?? false,
					'mail'                  => $result['mail'] ?? false,
					'mobile_phone'          => $result['mobilePhone'] ?? false,
					'office_location'       => $result['officeLocation'] ?? false,
					'preferred_language'    => $result['preferredLanguage'] ?? false,
					'surname'               => $result['surname'] ?? false,
					'user_principle_name'   => $result['userPrincipalName'] ?? false,
					'id'                    => $result['id'] ?? false,
					'account_enabled'       => $result['accountEnabled'],
				);
			}

			$data['actions']        = $this->action_dropdown($details['account_enabled']);
			$data['set_action']     = '';
			$data['client_code']    = $asset['code'];
			$data['user_id']        = $details['id'];

			$details['action_form'] = $this->load->view('ms-entra-browser/action-form', $data, TRUE);

			$response = array(
				'success'       => true,
				'results'       => [[
					'details'   => $details,
					'json'      => json_encode($results, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)
				]]
			);
		}
		else
		{
			$message = $this->get_error_message($response['response']);

			$response = array(
				'success'   => false,
				'message'   => $message
			);
		}

		echo json_encode($response);
	}

	public function perform_action()
	{
		$this->form_validation->set_rules('client_code', 'Client Code', 'trim|required');
		$this->form_validation->set_rules('user_id', 'User ID', 'trim|required');
		$this->form_validation->set_rules('entra_action', 'Action', 'trim|required');

		if ($this->form_validation->run()) 
		{
			$asset      = client_by_code($this->input->post('client_code'));
			$user_id    = $this->input->post('user_id');
			$action     = $this->input->post('entra_action');

			switch ($action)
			{
				case 'disable_acct':
				case 'enable_acct':
				case 'force_change_pw':
				case 'force_change_pw_with_mfa':
				case 'reset_pw_profile':
					$response = $this->ms_entra_api->update_user($asset['seed_name'], $user_id, $action);
					break;
				case 'revoke_sign_in_sessions':
					$response = $this->ms_entra_api->revoke_sign_in_sessions($asset['seed_name'], $user_id);
					break;
				case 'clear_auth_methods':
					$response = $this->ms_entra_api->clear_authentication_methods($asset['seed_name'], $user_id);
					break;
			}

			if ($response['success'])
			{
				switch ($action)
				{
					case 'disable_acct':
						$message    = 'Account has been successfully disabled.';
						break;
					case 'enable_acct':
						$message    = 'Account has been successfully enabled.';
						break;
					case 'force_change_pw':
						$message    = 'Force change password next sign in sucessfully completed.';
						break;
					case 'force_change_pw_with_mfa':
						$message    = 'Force change password next sign in with MFA sucessfully completed.';
						break;
					case 'revoke_sign_in_sessions':
						$message    = 'Revoke sign in sessions successfully completed. There might be a small delay of a few minutes before refresh tokens (as well as session cookies in a user\'s browser) are invalidated.';
						break;
					case 'clear_auth_methods':
						$message    = $response['response']['message'];
						break;
					case 'reset_pw_profile':
						$message    = 'Reset password profile sucessfully completed.';
						break;
				}

				$response = array(
					'success'   => true,
					'message'   => $message,
					'user_id'   => $user_id
				);
			}
			else
			{
				$message = $this->get_error_message($response['response']);

				$response = array(
					'success'   => false,
					'message'   => $message
				);
			}
		}
		else
		{
			$response = array(
				'success'       => false,
				'message'       => validation_errors(),
				'csrf_name'     => $this->security->get_csrf_token_name(),
				'csrf_value'    => $this->security->get_csrf_hash()
			);
		}

		echo json_encode($response);
	}

	private function ms_entra_enabled_customers()
	{
		$clients = NULL;

		$redis = new Redis();
		$redis->connect($this->redis_host, $this->redis_port, $this->redis_timeout);
		$redis->auth($this->redis_password);

			$results = $redis->sort('customer_list_active', [
				'by'    => '*->client',
				'alpha' => TRUE,
				'sort'  => 'asc',
				'get'   => [
					'*->client',
					'*->code',
					'*->seed_name',
					'*->ms_entra_enabled',
				]
			]);

		$redis->close();

		if (!empty($results))
		{
			$clients = array_chunk($results, 4);

			$clients = array_map(function($client) 
			{
				return array(
					'client'    => $client['0'],
					'code'      => $client['1'],
					'cust_seed' => $client['2'],
					'ms_entra'  => $client['3'],
				);
			}, $clients);

			foreach ($clients as $key => $value)
			{
				if (!$value['ms_entra'])
				{
					unset($clients[$key]);
				}
			}
		}

		return $clients;
	}

	private function search_type_dropdown()
	{
		$search_types = array(
			''                  => '- - - Select Search Type - - -',
			'email_contains'    => 'Email Contains',
			'email_equals'      => 'Email Equals',
			'list'              => 'List All Users',
			'enabled'           => 'List Enabled Users',
			'disabled'          => 'List Disabled Users',
			'name_contains'     => 'Name Contains',
			'name_starts_with'  => 'Name Starts With',
		);

		return $search_types;
	}

	private function action_dropdown($account_enabled)
	{
		$actions[''] = '- - - Select Action - - -';

		if ($account_enabled)
		{
			$actions['disable_acct']    = 'Disable Account';
		}
		else
		{
			$actions['enable_acct']     = 'Enable Account';
		}

		$actions['force_change_pw']             = 'Force Change Password Next Sign In';
		$actions['force_change_pw_with_mfa']    = 'Force Change Password Next Sign In With Mfa';
		$actions['revoke_sign_in_sessions']     = 'Revoke Sign In Sessions';
		$actions['clear_auth_methods']          = 'Clear Authentication Methods (Reset MFA)';
		//$actions['reset_pw_profile']            = 'Reset Password Profile';

		return $actions;
	}

	private function get_error_message($response)
	{
		$message = '';

		if (isset($response['error']['code'], $response['error']['message']))
		{
			$message    = 'Code: '.$response['error']['code'].'<br>Message: '.$response['error']['message'];
		}
		else
		{
			$message    = 'N/A';
		}

		return $message;
	}
}