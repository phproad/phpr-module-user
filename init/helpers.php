<?php

function user_logout($redirect = null)
{
	Phpr::$frontend_security->logout($redirect);
}