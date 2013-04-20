<?php

class User_Message_Recipient extends Db_ActiveRecord
{
	public $table_name = 'user_message_recipients';

	public $is_new = true;
	
	public $belongs_to = array(
		'user' => array('class_name' => 'User', 'foreign_key' => 'user_id'),
		'message' => array('class_name' => 'User_Message', 'foreign_key' => 'message_id'),
	);

	public function define_columns($context = null)
	{
		$this->define_relation_column('user', 'user', 'Recipient', db_varchar, '@username')->default_invisible();
		$this->define_relation_column('message', 'message', 'Message', db_varchar, '@subject')->default_invisible();
		$this->define_column('is_new', 'Unread');
		$this->define_column('deleted_at', 'Deleted At');        
	}

	public function delete_recipient()
	{
		Db_Helper::query("update user_message_recipients set deleted_at = now() where message_id=:message_id and user_id=:user_id", array(
			'message_id' => $this->message_id,
			'user_id' => $this->user_id
		));
	}
}