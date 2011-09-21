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

require_once(dirname(__FILE__) . "/../sys/models.php");
require_once(dirname(__FILE__) . "/../sys/pwhash.php");

$backend_subactions = url_action_subactions(array(
	"_default" => url_action_alias(array("login")),
	"_prelude" => function(&$data, $url_now, &$url_next)
	{
		global $ratatoeskr_settings;
		/* Check authentification */
		if(isset($_SESSION["uid"]))
		{
			try
			{
				$user = User::by_id($_SESSION["uid"]);
				if($user->pwhash == $_SESSION["pwhash"])
				{
					if(empty($user->language))
					{
						$user->language = $ratatoeskr_settings["default_language"];
						$user->save();
					}
					load_language($user->language);
					
					if($url_next[0] == "login")
						$url_next = array("content", "write");
					return; /* Authentification successful, continue  */
				}
				else
					unset($_SESSION["uid"]);
			}
			catch(DoesNotExistError $e)
			{
				unset($_SESSION["uid"]);
			}
		}
		load_language();
		/* If we are here, user is not logged in... */
		$url_next = array("login");
	},
	"index" => url_action_alias(array("login")),
	"login" => url_action_simple(function($data)
	{
		global $ste;
		if(!empty($_POST["user"]))
		{
			try
			{
				$user = User::by_name($_POST["user"]);
				if(!PasswordHash::validate($_POST["password"], $user->pwhash))
					throw new Exception();
				$_SESSION["uid"]    = $user->get_id();
				$_SESSION["pwhash"] = $user->pwhash;
			}
			catch(Exception $e)
			{
				$ste->vars["login_failed"] = True;
			}
			
			/* Login successful. Now redirect... */
			throw new Redirect(array("content", "write"));
		}
		
		echo $ste->exectemplate("systemtemplates/backend_login.html");
	}),
	"content" => url_action_simple(function($data)
	{
		print "hi";
	})
));

?>
