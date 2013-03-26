CREATE TABLE IF NOT EXISTS `user_messages` (
  `id` int(11) NOT NULL auto_increment,
  `from_user_id` int(11) DEFAULT NULL,  
  `thread_id` int(11) DEFAULT NULL,  
  `is_latest` tinyint(4) DEFAULT NULL,  
  `message` text,
  `subject` varchar(255) default NULL,
  `master_object_class` varchar(255) default NULL,
  `master_object_id` int(11) default NULL,
  `sent_at` datetime default NULL,
  `deleted_at` datetime default NULL,
PRIMARY KEY  (`id`),
KEY `thread_id` (`thread_id`),
KEY `from_user_id` (`from_user_id`),
KEY `master_index` (`master_object_class`,`master_object_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `user_message_recipients` (
  `message_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `is_new` tinyint(4) DEFAULT NULL,  
  `deleted_at` datetime default NULL,
PRIMARY KEY  (`message_id`, `user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;