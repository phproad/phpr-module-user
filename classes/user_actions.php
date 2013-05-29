<?php

class User_Actions extends User_Validate_Actions
{

	// User Login
	//
	
	public function login()
	{
		$redirect = $this->request_param(0);

		if ($redirect)
			$redirect = root_url(str_replace("|", "/", urldecode($redirect)));

		$this->data['redirect'] = $redirect;

		if (post('login'))
			$this->on_login();
		else if (post('register'))
			$this->on_register();
	}

	public function on_login()
	{
		if (post('flash'))
			Phpr::$session->flash['success'] = post('flash');

		$redirect = post('redirect');
		$validation = new Phpr_Validation();
		if (!Phpr::$frontend_security->login($validation, $redirect, post_array('User', 'login'), post_array('User', 'password'), 'login'))
		{
			$validation->add('login')->focus_id('user_login');
			$validation->set_error(__('Invalid email or password', true), 'login', true);
		}

		// If this is an ajax call, populate the native user object
		if (!$redirect)
		{
			$controller = Cms_Controller::get_instance();
			$controller->user = Phpr::$frontend_security->authorize_user();
		}
	}

	// User Register
	//
	
	public function register()
	{
		if (post('register'))
			$this->on_register();
	}

	public function on_register()
	{
		$user = new User();
		$user->disable_column_cache('register', false);
		$user->init_columns('register');
		$user->guest = false;
		
		$user->validation->focus_prefix = null;
		$user->validation->get_rule('email')->focus_id('email');

		if (!post_array('User', 'password'))
			$user->generate_password();

		if (post('user_no_password_confirm') && post_array('User', 'password'))
			$_POST['User']['password_confirm'] = post_array('User', 'password');

		// Fee check
		Phpr_Module_Manager::module_exists('payment') && Payment_Fee::trigger_event('User_Register_Event', array('handler'=>'user:on_register'));

		$user->save(post('User'));

		// Send notification
		Notify::trigger('user:register_confirm', array('user'=>$user));

		if (post('flash'))
			Phpr::$session->flash['success'] = post('flash');

		if (post('user_auto_login'))
			Phpr::$frontend_security->user_login($user->id);

		$redirect = post('redirect');
		if ($redirect)
			Phpr::$response->redirect($redirect);

		return $user;   
	}

	// User Reset Pass
	//
	
	public function reset_password()
	{
		if (post('password_reset'))
			$this->on_reset_password();
	}

	public function on_reset_password()
	{
		try
		{
			$validation = new Phpr_Validation();
			$validation->add('email', __('Email', true))->fn('trim')->required(__('Please specify your email address', true))->fn('mb_strtolower')->focus_id('user_email');
			if (!$validation->validate(post('User')))
				$validation->throw_exception();

			User::reset_password($validation->field_values['email']);

			if (post('flash'))
				Phpr::$session->flash['success'] = post('flash');

			$redirect = post('redirect');
			if ($redirect)
				Phpr::$response->redirect($redirect);
		}
		catch (Exception $ex)
		{
			throw new Cms_Exception($ex->getMessage());
		}
	}

	// User Manage
	//

	public function account()
	{
		if (!$this->user)
			throw new Cms_Exception(__('You must be logged in to perform this action', true));

		// Process file uploads
		$this->_process_user_upload($this->user);

		$this->user->load_user_preferences();
	}

	public function on_update_account()
	{
		if (!$this->user)
			throw new Cms_Exception(__('You must be logged in to perform this action', true));

		$validation = new Phpr_Validation();

		if ($reset_pass = post_array('User', 'password')) {
			$validation->add('old_password', 'Old Password')->fn('trim')->required(__('Please specify the old password', true));
			$validation->add('password', 'Password')->fn('trim')->required(__('Please specify new password', true));
			$validation->add('password_confirm', 'Confirm Password')->fn('trim')->matches('password', __('Password and confirmation password do not match.', true));
		}

		if (!$validation->validate(post('User')))
			$validation->throw_exception();

		if ($reset_pass && Phpr_SecurityFramework::create()->salted_hash($validation->field_values['old_password']) != $this->user->password)
			$validation->set_error(__('Current password entered is invalid', true), 'old_password', true);

		if ($reset_pass)
			unset($validation->field_values['old_password']);
		else
			$this->user->password = null;

		$this->user->save(post('User'));

		if (!post('no_flash', false))
			Phpr::$session->flash['success'] = __("Details saved successfully!", true);

		$redirect = post('redirect');
		if ($redirect)
			Phpr::$response->redirect($redirect);
	}

	public function on_update_avatar()
	{
		$avatar_id = post('user_avatar');

		// Delete avatar
		if (post('delete')||$avatar_id)
			$this->user->delete_avatar();

		// Find orphaned avatar
		if ($avatar_id)
		{
			$avatar = Db_File::create()->where('master_object_id is null')->find($avatar_id);
			if ($avatar)
				$this->user->avatar->add($avatar);
		}

		$this->user->save();
	}    

	// Preferences
	// 

	public function on_update_preferences()
	{
		if (!$this->user)
			throw new Cms_Exception(__('You must be logged in to perform this action', true));

		$this->user->load_user_preferences();
		
		foreach ($this->user->added_preference_fields as $field=>$value)
		{
			$this->user->$field = post_array('User', $field);
		}

		$this->user->password = null;
		$this->user->save();

		if (!post('no_flash', false))
			Phpr::$session->flash['success'] = __("Details saved successfully!", true);

		$redirect = post('redirect');
		if ($redirect)
			Phpr::$response->redirect($redirect);
	}

	// Messaging
	//
	
	public function messages()
	{
		$messages = User_Message::create();
		$messages->apply_user_messages($this->user);
		$this->data['messages'] = $messages;
	}

	public function message()
	{
		$this->on_message();

		$opp_user_string = (isset($this->data['opp_user_string'])) ? $this->data['opp_user_string'] : '???';
		$this->page->title_name = __('Conversation with %s', $opp_user_string);
	}

	public function on_message()
	{
		try 
		{
			$message_id = post('message_id', $this->request_param(0));

			if (!$message_id)
				throw new Exception('Missing message id');

			$message = User_Message::create()->find($message_id);
			if (!$message)
				throw new Exception('Message not found');

			$message_thread = $message->get_thread();
			$messages = User_Message::create();
			$messages = $messages->apply_thread($message_thread->id)->find_all();

			if (!$message_thread->is_recipient($this->user))
				throw new Exception('Not authorised to view this message');

			if (!$messages)
				throw new Exception('No messages found');

			$message_thread->mark_as_read($this->user);

			$opp_user_string = $message_thread->get_other_recipients_string($this->user);
			$this->data['message'] = $message;
			$this->data['messages'] = $messages;
			$this->data['opp_user_string'] = $opp_user_string;
		}
		catch (Exception $ex)
		{
			$this->data['message'] = $this->data['messages'] = null;
		}        
	}

	public function on_send_message()
	{
		$_POST = array_merge($_POST, post('Message', array()));

		if (!$this->user)
			throw new Cms_Exception(__('You must be logged in to perform this action', true));

		$is_reply = false;
		if ($thread_id = post_array('Message', 'thread_id'))
		{
			$message_thread = User_Message::create()->find($thread_id);
			if ($message_thread)
				$is_reply = true;
		}

		$validation = new Phpr_Validation();

		$to_user_field = $validation->add('to_user_id', 'Recipient')->fn('trim');
		$validation->add('message', 'Message')->fn('trim')->required(__('Please specify a message', true));
		$validation->add('subject', 'Message')->fn('trim');
		$validation->add('object_class', 'Object Class')->fn('trim');
		$validation->add('object_id', 'Object ID')->fn('trim');
		$validation->add('thread_id', 'Thread ID')->fn('trim');

		if (!$is_reply)
			$to_user_field->required(__('No recipient defined', true));

		if (!$validation->validate(post('Message')))
			$validation->throw_exception();

		$object_class = $is_reply ? $message_thread->master_object_class : $validation->field_values['object_class'];
		$object_id = $is_reply ? $message_thread->master_object_id : $validation->field_values['object_id'];

		$message = User_Message::create();
		$message->master_object_class = $object_class;
		$message->master_object_id = $object_id;
		$message->from_user = $this->user;
		$message->save($validation->field_values);

		if (!$is_reply)
			$message->add_recipients(array($validation->field_values['to_user_id'], $this->user->id));
		else
			$message_thread->mark_as_unread($this->user);

		// Notify other recipients
		foreach ($message->get_other_recipients($this->user) as $recipient) {
			Notify::trigger('user:new_message', array('message'=>$message, 'user'=>$recipient->user));
		}

		if (!post('no_flash', false))
			Phpr::$session->flash['success'] = __("Message sent successfully!", true);

		$redirect = post('redirect');
		if ($redirect)
			return Phpr::$response->redirect($redirect);

		$thread = ($is_reply) ? $message_thread : $message;
		$messages = User_Message::create();

		$messages = $messages->apply_thread($thread)->find_all();
		$this->data['message'] = $message;
		$this->data['messages'] = $messages;
	}

	public function on_search_messages()
	{
		if (!$this->user)
			throw new Cms_Exception(__('You must be logged in to perform this action', true));

		$messages = User_Message::create();
		$messages = $messages->apply_user_messages($this->user);

		$where = Db_Helper::format_search_query(post('search'), array('user_messages.message', 'user_messages.subject'), 2);
		if ($where != '1=1')
			$messages->where($where);

		$this->data['messages'] = $messages;
	}

	public function on_delete_message()
	{
		if (!$this->user)
			throw new Cms_Exception(__('You must be logged in to perform this action', true));

		$message_id = post('message_id');
		User_Message::delete_message_from_id($message_id, $this->user->id);

		if (!post('no_flash', false))
			Phpr::$session->flash['success'] = __("Message sent successfully!", true);

		$redirect = post('redirect');
		if ($redirect)
			Phpr::$response->redirect($redirect);

		// Refresh page data
		// 
		$this->messages();
	}

	// Internals
	// 

	private function _process_user_upload($user)
	{
		$result = array();
		$detect_upload = false;

		// Avatar
		if (array_key_exists('user_avatar', $_FILES))
		{            
			$detect_upload = true;
			$post_post = $_FILES['user_avatar'];

			// Determine size
			$size = Phpr_String::dimension_from_string(post('user_avatar_size', 100));

			$file = $user->save_attachment_from_post('avatar', $post_post, true);
			$result = array(
				'id' => $file->id,
				'thumb'=> (($file->is_image()) ? $file->getThumbnailPath($size['width'], $size['height'], true, array('mode'=>'crop')) : null)
			);
		}

		if ($detect_upload)
		{
			$user->save(null, post('session_key'));
			echo json_encode($result);
			die();
		}
	}

}