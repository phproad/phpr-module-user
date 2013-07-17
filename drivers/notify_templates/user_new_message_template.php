<?php

class User_New_Message_Template extends Notify_Template_Base
{
	public $required_params = array('message', 'user');

	public function get_info()
	{
		return array(
			'name'=> 'Message Received',
			'description' => 'Notifies the user when they receive a private message.',
			'code' => 'user:new_message'
		);
	}

	public function get_subject()
	{
		return '{from_user_display_name} has sent you a message on {site_name}';
	}

	public function get_content()
	{
		return file_get_contents($this->get_partial_path('content.htm'));
	}

	public function prepare_template($template, $params=array())
	{
		extract($params);

		$user->set_notify_vars($template, 'user_');
		$message->set_notify_vars($template, 'message_');
		
		$from_user = $message->from_user;
		$from_user->set_notify_vars($template, 'from_user_');

		// No point sending this to self
		if ($user->id == $from_user->id)
			return;

		$template->set_vars(array());

		$template->add_recipient($user);
	}
}
