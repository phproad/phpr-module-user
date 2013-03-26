<?php

class User_Register_Event extends Payment_Fee_Event_Base
{
	public function get_info()
	{
		return array(
			'name' => 'User Register',
			'description' => 'User signs up to the site.'
		);
	}
}
