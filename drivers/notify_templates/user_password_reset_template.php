<?php

class User_Password_Reset_Template extends Notify_Template_Base
{
	public $required_params = array('user');

	public function get_info()
	{
		return array(
			'name'=> 'Reset User Password',
			'description' => 'Notifies the user when they reset their password.',
			'code' => 'user:password_reset'
		);
	}

	public function get_subject()
	{
		return 'New password';
	}

	public function get_content()
	{
		return file_get_contents($this->get_partial_path('content.htm'));
	}

	public function prepare_template($template, $params=array())
	{
		extract($params);

		$user->set_notify_vars($template, 'user_');
		$template->set_vars(array());

		$template->add_recipient($user);
	}
}
