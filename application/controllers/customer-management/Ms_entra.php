<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Ms_entra extends CI_Controller 
{
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

		$this->utility->restricted_access();
		$this->load->model('account/account_model', 'account');
		$this->load->library('ms_entra_api');
	}

	function _remap($method, $args)
	{ 
		if (method_exists($this, $method))
		{
			$this->$method($args);
		}
		else
		{
			$this->index($method, $args);
		}
	}

	public function index($method, $args = array())
	{
		$asset = client_redis_info_by_code();

		# Page Data	
		$nav['client_name']         = $asset['client'];
		$nav['client_code']         = $asset['code'];

		$data['client_code']        = $asset['code'];
		$data['sub_navigation']     = $this->load->view('customer-management/navigation', $nav, TRUE);	
		$data['ms_entra_info']      = $this->ms_entra_api->redis_info($asset['seed_name']);
		$data['show_activation']    = FALSE;
		$data['api_tested']         = FALSE;
		$data['request_was_sent']   = FALSE;
		$data['api_enabled']        = FALSE;
		$data['action']             = 'create';

		if (!is_null($data['ms_entra_info']))
		{
			$data['action'] = 'modify';

			if (intval($data['ms_entra_info']['tested']))
			{
				$data['show_activation']    = TRUE;
				$data['api_tested']         = TRUE;

				if (intval($data['ms_entra_info']['request_sent']))
				{
					$data['request_was_sent'] = TRUE;
				}

				if (intval($data['ms_entra_info']['enabled']))
				{
					$data['api_enabled'] = TRUE;
				}
			}
		}

		# Page Views
		$this->load->view('assets/header');	
		$this->load->view('customer-management/ms_entra/start', $data);
		$this->load->view('assets/footer');
	}

	public function create()
	{
		$asset = client_redis_info_by_code();

		if ($this->input->method(TRUE) === 'POST')
		{
			$this->form_validation->set_rules('tenant_id', 'Tenant ID', 'trim|required');
			$this->form_validation->set_rules('client_id', 'Client ID', 'trim|required');
			$this->form_validation->set_rules('client_secret', 'Client Secret', 'trim|required');

			if ($this->form_validation->run()) 
			{
				$redis_data = array(
					'tenant_id'     => $this->input->post('tenant_id'),
					'client_id'     => $this->input->post('client_id'),
					'client_secret' => $this->input->post('client_secret')
				);

				if ($this->ms_entra_api->create_ms_entra_redis_key($asset['seed_name'], $redis_data))
				{
					# Write To Logs
					$log_message = '[MS Entra ID API Created] user: '.$this->session->userdata('username').' | for client: '.$asset['client'];
					$this->utility->write_log_entry('info', $log_message);

					# Success
					$this->session->set_userdata('my_flash_message_type', 'success');
					$this->session->set_userdata('my_flash_message', '<p>MS Entra ID API settings were successfully created.</p>');

					redirect('/customer-management/ms-entra/'.$asset['code']);
				}
				else
				{
					# Something went wrong
					$this->session->set_userdata('my_flash_message_type', 'error');
					$this->session->set_userdata('my_flash_message', '<p>Something went wrong. Please try again.</p>');
				}
			}
			else
			{
				if (validation_errors()) 
				{
					$this->session->set_userdata('my_flash_message_type', 'error');
					$this->session->set_userdata('my_flash_message', validation_errors());
				}
			}
		}

		# Page Data
		$data['client_code'] = $asset['code'];

		# Page Views
		$this->load->view('assets/header');
		$this->load->view('customer-management/ms_entra/create', $data);
		$this->load->view('assets/footer');
	}

	public function modify()
	{
		$asset = client_redis_info_by_code();

		if ($this->input->method(TRUE) === 'POST')
		{
			$this->form_validation->set_rules('tenant_id', 'Tenant ID', 'trim|required');
			$this->form_validation->set_rules('client_id', 'Client ID', 'trim|required');
			$this->form_validation->set_rules('client_secret', 'Client Secret', 'trim|required');

			if ($this->form_validation->run())
			{
				$redis_data = array(
					'tenant_id'     => $this->input->post('tenant_id'),
					'client_id'     => $this->input->post('client_id'),
					'client_secret' => $this->input->post('client_secret')
				);

				if ($this->ms_entra_api->create_ms_entra_redis_key($asset['seed_name'], $redis_data))
				{
					# Write To Logs
					$log_message = '[MS Entra ID API Modified] user: '.$this->session->userdata('username').' | for client: '.$asset['client'];
					$this->utility->write_log_entry('info', $log_message);

					# Success
					$this->session->set_userdata('my_flash_message_type', 'success');
					$this->session->set_userdata('my_flash_message', '<p>MS Entra ID API settings were successfully updated.</p>');

					redirect('/customer-management/ms-entra/'.$asset['code']);
				}
				else
				{
					# Something went wrong
					$this->session->set_userdata('my_flash_message_type', 'error');
					$this->session->set_userdata('my_flash_message', '<p>Something went wrong. Please try again.</p>');
				}
			}
			else
			{
				if (validation_errors()) 
				{
					$this->session->set_userdata('my_flash_message_type', 'error');
					$this->session->set_userdata('my_flash_message', validation_errors());
				}
			}
		}

		# Page Data
		$data['client_code']    = $asset['code'];
		$data['ms_entra_info']  = $this->ms_entra_api->redis_info($asset['seed_name']);

		# Page Views
		$this->load->view('assets/header');
		$this->load->view('customer-management/ms_entra/modify', $data);
		$this->load->view('assets/footer');
	}

	public function api_test()
	{
		$asset = client_redis_info_by_code();

		$search_params = array(
			'type'  => 'enabled'
		);

		$response = $this->ms_entra_api->get_users_by_filter($asset['seed_name'], $search_params);

		if ($response['success'])
		{
			$results        = [];
			$result_count   = count($response['response']['value']);

			foreach ($response['response']['value'] as $result)
			{
				$results[] = array(
					'display_name'  => $result['displayName'],
					'job_title'     => $result['jobTitle'] ?? 'N/A',
					'mail'          => $result['mail'] ?? 'N/A'
				);
			}

			if ($result_count)
			{
				$this->ms_entra_api->redis_info($asset['seed_name'], NULL, 'SET', array('tested' => '1'));
			}

			$return_array = array(
				'success'       => TRUE,
				'response'      => $response['response'],
				'results'       => $results,
				'result_count'  => $result_count
			);
		}
		else
		{
			$return_array = array(
				'success'   => FALSE,
				'response'  => $response['response']
			);
		}

		echo json_encode($return_array);
	}

	public function activate()
	{
		$asset = client_redis_info_by_code();

		$ms_entra_info                  = $this->ms_entra_api->redis_info($asset['seed_name']);
		$data['authorized_to_modify']   = $this->account->get_authorized_to_modify($asset['id']);
		$data['client_code']            = $asset['code'];
		$data['client_title']           = $asset['client'];
		$data['requested']              = $ms_entra_info['request_sent'];
		$data['request_user']           = $ms_entra_info['request_user'] ?? NULL;
		$data['terms_agreed']           = intval($ms_entra_info['terms_agreed']);

		$this->load->view('customer-management/ms_entra/activate', $data);
	}

	public function do_activate()
	{
		$asset = client_redis_info_by_code();

		$this->form_validation->set_rules('requesting_user', 'Requesting Contact', 'trim|required');
		$this->form_validation->set_rules('api-terms-of-agreement', 'api-terms-of-agreement', 'trim|required');

		if ($this->form_validation->run()) 
		{
			$requested_by   = $this->input->post('requesting_user');			
			$requested_user = $this->account->get_user_by_code($requested_by);
			$requested_name = $requested_user->first_name.' '.$requested_user->last_name;

			if ($this->ms_entra_api->change_api_activation_status($asset['seed_name'], $requested_by, TRUE))
			{
				$this->account->send_api_activation_notification($asset['id'], 'ms_entra', $requested_name);

				# Write To Logs
				$log_message = '[MS Entra ID API Enabled] user: '.$this->session->userdata('username').', has enabled api for customer: '.$asset['client'].', per the request of '.$requested_name;
				$this->utility->write_log_entry('info', $log_message);

				# Set Success Alert Response
				$this->session->set_userdata('my_flash_message_type', 'success');
				$this->session->set_userdata('my_flash_message', '<p>The MS Entra ID API for: <strong>'.$asset['client'].'</strong>, has been successfully enabled.</p>');

				$response = array(
					'success'   => true,
					'goto_url'  => '/customer-management/ms-entra/'.$asset['code']
				);
				echo json_encode($response);
			}
			else
			{
				# Set Error
				$response = array(
					'success'   => false,
					'message'   => '<p>Something went wrong. Please try again.</p>'
				);
				echo json_encode($response);
			}
		}
		else
		{
			if (validation_errors()) 
			{
				# Set Error
				$response = array(
					'success'   => false,
					'message'   => validation_errors()
				);
				echo json_encode($response);
			}
		}
	}

	public function disable()
	{
		$asset = client_redis_info_by_code();

		$data['authorized_to_modify']   = $this->account->get_authorized_to_modify($asset['id']);
		$data['client_code']            = $asset['code'];
		$data['client_title']           = $asset['client'];

		$this->load->view('customer-management/ms_entra/disable', $data);
	}

	public function do_disable()
	{
		$asset = client_redis_info_by_code();

		$this->form_validation->set_rules('requesting_user', 'Requesting Contact', 'trim|required');
		$this->form_validation->set_rules('api-terms-of-agreement', 'api-terms-of-agreement', 'trim|required');

		if ($this->form_validation->run()) 
		{
			$requested_by   = $this->input->post('requesting_user');			
			$requested_user = $this->account->get_user_by_code($requested_by);
			$requested_name = $requested_user->first_name.' '.$requested_user->last_name;

			if ($this->ms_entra_api->change_api_activation_status($asset['seed_name'], $requested_by, FALSE))
			{
				$this->account->send_api_disabled_notification($asset['id'], 'ms_entra', $requested_name);

				# Write To Logs
				$log_message = '[MS Entra ID API Disabled] user: '.$this->session->userdata('username').', has disabled api for customer: '.$asset['client'].', per the request of '.$requested_name;
				$this->utility->write_log_entry('info', $log_message);

				# Set Success Alert Response
				$this->session->set_userdata('my_flash_message_type', 'success');
				$this->session->set_userdata('my_flash_message', '<p>The MS Entra ID API for: <strong>'.$asset['client'].'</strong>, has been successfully disabled.</p>');

				$response = array(
					'success'   => true,
					'goto_url'  => '/customer-management/ms-entra/'.$asset['code']
				);
				echo json_encode($response);
			}
			else
			{
				# Set Error
				$response = array(
					'success'   => false,
					'message'   => '<p>Something went wrong. Please try again.</p>'
				);
				echo json_encode($response);
			}
		}
		else
		{
			if (validation_errors()) 
			{
				# Set Error
				$response = array(
					'success'   => false,
					'message'   => validation_errors()
				);
				echo json_encode($response);
			}
		}
	}
}