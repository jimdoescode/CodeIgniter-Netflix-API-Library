<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Example extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->library('session');
	}
	
	//CALL THIS METHOD FIRST BY GOING TO
	//www.your_url.com/index.php/request_netflix
	public function request_netflix()
	{
		$params['key'] = 'NETFLIX CONSUMER KEY';
		$params['secret'] = 'NETFLIX CONSUMER SECRET';
		$this->load->library('netflix', $params);
		
		$data = $this->netflix->get_request_token(site_url("welcome/access_netflix"));
		
		$this->session->set_userdata('token_secret', $data['token_secret']);
		redirect($data['redirect']);
	}
	
	//This method will be redirected to automatically
	//once the user approves access of your application
	public function access_netflix()
	{
		$params['key'] = 'NETFLIX CONSUMER KEY';
		$params['secret'] = 'NETFLIX CONSUMER SECRET';
		$this->load->library('netflix', $params);
		
		$oauth = $this->netflix->get_access_token($this->session->userdata('token_secret'));
		
		$this->session->set_userdata('oauth_token', $oauth['oauth_token']);
		$this->session->set_userdata('oauth_token_secret', $oauth['oauth_token_secret']);
	}
	
	public function netflix_no_auth()
	{
		$params['key'] = 'NETFLIX CONSUMER KEY';
		$params['secret'] = 'NETFLIX CONSUMER SECRET';
		$this->load->library('netflix', $params);
		
		echo $this->netflix->search_title('Jurassic Park');
	}
	
	public function netflix_auth()
	{
		$params['key'] = 'NETFLIX CONSUMER KEY';
		$params['secret'] = 'NETFLIX CONSUMER SECRET';
		$params['access'] = array('oauth_token'=>urlencode($this->session->userdata('oauth_token')),
								  'oauth_token_secret'=>urlencode($this->session->userdata('oauth_token_secret')));
		$this->load->library('netflix', $params);
		
		echo $this->netflix->get_user();
	}
}

/* End of file example.php */
/* Location: ./application/controllers/example.php */