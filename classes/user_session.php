<?php

class User_Session
{
	public static function set($name, $value)
	{
		$params = Phpr::$session->get('user_session', array());
		$params[$name] = serialize($value);
		
		Phpr::$session->set('user_session', $params);
	}

	public static function get($name, $default = null)
	{
		$params = Phpr::$session->get('user_session', array());
		
		if (array_key_exists($name, $params)) {
			
			$value = $params[$name];
			
			if (strlen($value)) {
				try {
					return @unserialize($value);
				} 
				catch (Exception $ex){ }
			}
			
			return $value;
		}
		
		return $default;
	}
}
