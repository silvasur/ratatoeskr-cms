<?php
/*
 * File: ratatoeskr/backend/main.php
 * Main file for the backend.
 * 
 * License:
 * This file is part of Ratatöskr.
 * Ratatöskr is licensed unter the MIT / X11 License.
 * See "ratatoeskr/licenses/ratatoeskr" for more information.
 */

require_once(dirname(__FILE__) . "/../sys/");



$backend_subactions = url_action_subactions(array(
	"_default" => url_action_alias(array("login")),
	"login" => url_action_simple(function($data)
	{
		
	}),
	
));

?>
