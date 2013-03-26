<?php

class User_Register_Confirm_Template extends Notify_Template_Base
{
	public $required_params = array('user');

	public function get_info()
	{
		return array(
			'name'=> 'User Registration Confirmation',
			'description' => 'Sent after a user successfully registers.',
			'code' => 'user:register_confirm'
		);
	}

	public function get_subject()
	{
		return 'Registration confirmation';
	}

	public function get_content()
	{
		return file_get_contents($this->get_partial_path('content.htm'));
	}

	public function get_internal_subject()
	{
		return 'New user has joined!';
	}

	public function get_internal_content()
	{
		return file_get_contents($this->get_partial_path('internal_content.htm'));
	}

	public function get_external_subject()
	{
		return '{user_name} has decided to join {site_name}!';
	}

	public function prepare_template($template, $params=array())
	{
		extract($params);

		$user->set_notify_vars($template, 'user_');
		$template->set_vars(array());

		$template->add_recipient($user);
	}
}
