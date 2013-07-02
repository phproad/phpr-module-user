<?php

class User_Message extends Db_ActiveRecord
{
	public $table_name = 'user_messages';

	public $belongs_to = array(
		'thread' => array('class_name' => 'User_Message', 'foreign_key' => 'thread_id'),
		'from_user' => array('class_name' => 'User', 'foreign_key' => 'from_user_id'),
	);

	public $has_many = array(
		'recipients'=>array('class_name'=>'User_Message_Recipient', 'order'=>'user_message_recipients.user_id', 'foreign_key'=>'message_id', 'delete'=>true)
	);

	public $calculated_columns = array(
		'message_thread_id' => array('sql' => "ifnull(user_messages.thread_id, user_messages.id)", 'type' => db_number),
	);

	public $custom_columns = array(
		'message_summary' => db_varchar,
		'message_html' => db_varchar,
	);

	public function define_columns($context = null)
	{
		$this->define_column('id', '#');
		$this->define_column('message', 'Message');
		$this->define_column('subject', 'Subject');
		$this->define_relation_column('recipients', 'recipients', 'Recipients', db_varchar, '@user_id')->default_invisible();
		$this->define_relation_column('from_user', 'from_user', 'From', db_varchar, '@username')->default_invisible();
		$this->define_column('sent_at', 'Sent At');
	}

	public function define_form_fields($context = null)
	{
		$this->add_form_field('message','left');
		$this->add_form_field('subject','left');
	}

	// Events
	// 

	public function before_create($session_key = null)
	{
		if ($this->thread_id)
			self::reset_thread_latest($this->thread_id);

		$this->is_latest = true;
		$this->sent_at = Phpr_DateTime::now()->to_sql_datetime();
	}

	// Filters
	// 

	// Finds a message thread (parent message)
	public function apply_thread($message)
	{
		$message_id = ($message instanceof User_Message) ? $message->id : $message;
		$thread_id = ($message instanceof User_Message) ? $message->thread_id : $message;
		$bind = array('message_id' => $message_id, 'thread_id' => $thread_id);
		$this->where('user_messages.thread_id=:thread_id or user_messages.id=:message_id', $bind);

		return $this;
	}

	// Finds threads for a user
	public function apply_user_threads($user)
	{
		$bind = array(
			'user_id' => $user->id
		);

		$this->join('user_message_recipients', 'user_message_recipients.message_id = ifnull(user_messages.thread_id, user_messages.id) AND user_message_recipients.user_id='.$user->id);
		$this->where('user_messages.from_user_id=:user_id OR user_message_recipients.user_id=:user_id', $bind);
		$this->where('user_messages.is_latest=1');
		$this->order('user_messages.sent_at desc');
		$this->group('user_messages.id');

		$this->where('user_messages.deleted_at is null');
		$this->where('user_message_recipients.deleted_at is null');

		// Include recipients_string
		$this->select("(select group_concat(distinct u.username separator ', ') from user_message_recipients as umr
			join user_messages as um on um.id = umr.message_id 
			join users as u on u.id = umr.user_id OR u.id = um.from_user_id 
			where umr.message_id = ifnull(user_messages.thread_id, user_messages.id)
			and u.id != '".$user->id."'
			) as recipients_string,
			user_message_recipients.is_new as is_new
		");

		return $this;
	}

	// Finds inbox or sent items for a user (no threads)
	public function apply_user_messages($user=null, $sent_items=false)
	{
		$bind = array('user_id' => $user->id);
		$this->join('user_message_recipients', 'user_message_recipients.message_id = user_messages.id');
		$this->where('deleted_at is null');
		
		if ($sent_items)
			$this->where('user_messages.from_user_id=:user_id', $bind);
		else
			$this->where('user_message_recipients.user_id=:user_id', $bind);

		$this->order('sent_at desc');
		return $this;
	}

	// Getters
	// 

	public function get_thread()
	{
		if ($this->thread_id && $this->thread)
			return $this->thread;
		else
			return $this;
	}

	public function get_url($page=null, $add_hostname=false)
	{
		if (!$page) 
			$page = Cms_Page::get_url_from_action('user:message');

		return root_url($page.'/'.$this->id, $add_hostname);
	}

	public function get_other_recipients($user)
	{
		$thread = $this->get_thread();
		$recipients = $thread->recipients;
		if ($this->from_user_id == $user->id)
			return $recipients;

		$recipients = $thread->recipients->exclude($user->id, 'user_id');
		$recipients = $recipients->add($this->from_user);
		return $recipients;
	}

	public function get_other_recipients_string($user)
	{
		$bind = array(
			'user_id' => $user->id,
			'message_id' => ($this->thread_id) ? $this->thread_id : $this->id
		);
		return Db_Helper::scalar("select group_concat(distinct users.username separator ', ') from user_message_recipients
			join user_messages on user_messages.id = user_message_recipients.message_id 
			join users on users.id = user_message_recipients.user_id OR users.id = user_messages.from_user_id 
			where user_message_recipients.message_id = :message_id
			and users.id != :user_id
			", $bind);
	}

	// Service methods
	// 

	public function add_recipients($users=null)
	{
		if (!$users)
			return;

		if (!is_array($users))
			$users = array($users);

		// Prevent duplicates
		$users = array_unique($users);

		$thread = $this->get_thread();

		foreach ($users as $user) {
			$recipient = User_Message_Recipient::create();
			$recipient->user_id = $user;
			$recipient->message_id = $thread->id;
			$recipient->is_new = ($user != $this->from_user_id);
			$recipient->save();
			$thread->recipients->add($recipient);
		}

		return $this;
	}

	public function mark_as_read($context_user=null)
	{
		if (!$context_user)
			return $this;

		$bind = array(
			'message_id' => $this->id,
			'user_id' => $context_user->id
		);

		Db_Helper::query('update user_message_recipients set is_new=null where user_id=:user_id AND message_id=:message_id', $bind);
		return $this;
	}

	public function mark_as_unread($context_user=null)
	{
		if (!$context_user)
			return $this;

		$bind = array(
			'message_id' => $this->id,
			'user_id' => $context_user->id
		);

		Db_Helper::query('update user_message_recipients set is_new=1 where user_id!=:user_id AND message_id=:message_id', $bind);
		return $this;
	}

	public static function check_new_messages($user=null)
	{
		if (!$user)
			return null;

		$bind = array('user_id' => $user->id);
		return Db_Helper::scalar('select count(*) from user_message_recipients where user_id=:user_id and is_new = 1', $bind);
	}

	public function is_recipient($user)
	{
		$recipients = $this->recipients;
		if ($this->from_user_id == $user->id)
			return true;

		return $recipients->find($user->id, 'user_id') ? true : false;
	}

	public static function reset_thread_latest($thread_id)
	{
		$bind = array('thread_id' => $thread_id);
		Db_Helper::query('update user_messages set is_latest = null where is_latest = 1 and (thread_id=:thread_id or id=:thread_id)', $bind);
	}

	public static function delete_message_from_id($message_id, $user_id)
	{
		$recipient = User_Message_Recipient::create()->where('user_id=?', $user_id)->where('message_id=?', $message_id)->find();
		if ($recipient)
		{
			$recipient->delete_recipient();
		}

		$message = User_Message::create()->where('from_user_id=?', $user_id)->find($message_id);
		if ($message)
		{
			$message->deleted_at = Phpr_DateTime::now();
			$message->save();
		}
	}

	public static function delete_message_from_object($object_class, $object_id)
	{
		$bind = array('object_id'=>$object_id, 'object_class'=>$object_class);
		$message_ids = Db_Helper::query_array("select id from user_messages where (user_messages.master_object_id=:object_id) AND (user_messages.master_object_class=:object_class)", $bind);

		if (!count($message_ids))
			return;
		
		Db_Helper::query("delete from user_message_recipients where message_id in (:id)", array('id'=>$message_ids));
		Db_Helper::query("delete from user_messages where id in (:id)", array('id'=>$message_ids));
	}

	public function set_notify_vars(&$template, $prefix='')
	{
		$message_url = $this->get_url(null, true);

		$template->set_vars(array(
			$prefix.'url' => '<a href="'.$message_url.'">'.h($message_url).'</a>',
			$prefix.'link' => '<a href="'.$message_url.'">'.h($message_url).'</a>',
		), false);

		$template->set_vars(array(
			$prefix.'message' => $this->message,
			$prefix.'subject' => $this->subject,
			$prefix.'sent_at' => Phpr_DateTime::format_safe($this->sent_at, '%x'),
		));
	}

	// Custom columns
	//

	public function eval_message_summary()
	{
		return Phpr_String::limit_words($this->message, 35);
	}

	public function eval_message_html()
	{
		if (strlen($this->message))
			return Phpr_Html::paragraphize($this->message);
		else
			return null;
	}
}
