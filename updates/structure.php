<?php

$table = Db_Structure::table('users');
	$table->primary_key('id');
	$table->column('username', db_varchar, 100);
	$table->column('first_name', db_varchar, 100);
	$table->column('last_name', db_varchar, 100);
	$table->column('email', db_varchar, 50);
	$table->column('password', db_varchar, 50);
	$table->column('guest', db_bool)->set_default(true);
	$table->column('enabled', db_bool);
	$table->column('signup_ip', db_varchar, 15);
	$table->column('last_ip', db_varchar, 15);
	$table->column('group_id', db_number)->index();
	$table->footprints();
	$table->column('deleted_at', db_datetime);

$table = Db_Structure::table('user_preferences');
	$table->primary_key('id');
	$table->column('name', db_varchar, 100);
	$table->column('value', db_varchar);
	$table->column('user_id', db_number)->index();
	$table->column('module_id', db_varchar, 50);
	$table->add_key('user_preference', array('user_id', 'module_id', 'name'))->unique();

$table = Db_Structure::table('user_groups');
	$table->primary_key('id');
	$table->column('name', db_varchar, 100);
	$table->column('code', db_varchar, 50)->index();
	$table->column('description', db_text);
