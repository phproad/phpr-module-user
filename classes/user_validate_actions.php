<?php

class User_Validate_Actions extends Cms_Action_Base
{

	// User validation functions
	// 
	public function on_validate_password()
	{
		$valid = false;
		$old_password = post_array('User', 'old_password');

		if (!$old_password)
			return;

		try
		{
			$validation = new Phpr_Validation();
			$valid = (Phpr_SecurityFramework::create()->salted_hash($old_password) == $this->user->password);
			
			if (!$valid)
				$validation->set_error(__('Password entered is invalid',true), 'old_password', true);
		}
		catch (Exception $ex)
		{
			$valid = false;
			echo $ex->getMessage();
		}

		if ($valid)
			echo "true";
	}

	public function on_validate_login()
	{
		$valid = false;
		$login = post_array('User', 'login');
		$password = post_array('User', 'password');

		if (!$password || !$login)
			return;

		try
		{
			$validation = new Phpr_Validation();
			$valid = User::create()->find_user($login, $password);
			
			if (!$valid)
				$validation->set_error(__('Password entered is invalid',true), 'password', true);
		}
		catch (Exception $ex)
		{
			$valid = false;
			echo $ex->getMessage();
		}

		if ($valid)
			echo "true";
	}

	public function on_validate_username()
	{
		$valid = false;
		$username = post_array('User', 'username');

		try
		{
			$valid = User::create()->validate_username('username', $username);
		}
		catch (Exception $ex)
		{
			$valid = false;
			echo $ex->getMessage();
		}

		if ($valid)
			echo "true";        
	}

	public function on_validate_email()
	{
		$valid = false;
		$email = post_array('User', 'email');

		try
		{
			$valid = User::create()->validate_email('email', $email);
		}
		catch (Exception $ex)
		{
			$valid = false;
			echo $ex->getMessage();
		}

		if ($valid)
			echo "true";        
	}
}