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

$admin_grp = Group::by_name("admins");

$backend_subactions = url_action_subactions(array(
	"_index" => url_action_alias(array("login")),
	"index" => url_action_alias(array("login")),
	/* _prelude guarantees that the user is logged in properly, so we do not have to care about that later. */
	"_prelude" => function(&$data, $url_now, &$url_next)
	{
		global $ratatoeskr_settings, $admin_grp, $ste;
		
		/* Check authentification */
		if(isset($_SESSION["ratatoeskr_uid"]))
		{
			try
			{
				$user = User::by_id($_SESSION["ratatoeskr_uid"]);
				if(($user->pwhash == $_SESSION["ratatoeskr_pwhash"]) and $user->member_of($admin_grp))
				{
					if(empty($user->language))
					{
						$user->language = $ratatoeskr_settings["default_language"];
						$user->save();
					}
					load_language($user->language);
					
					if($url_next[0] == "login")
						$url_next = array("content", "write");
					$data["user"] = $user;
					$ste->vars["user"] = array("name" => $user->username);
					return; /* Authentification successful, continue  */
				}
				else
					unset($_SESSION["ratatoeskr_uid"]);
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
	"login" => url_action_simple(function($data)
	{
		global $ste, $admin_grp;
		if(!empty($_POST["user"]))
		{
			try
			{
				$user = User::by_name($_POST["user"]);
				if(!PasswordHash::validate($_POST["password"], $user->pwhash))
					throw new Exception();
				if(!$user->member_of($admin_grp))
					throw new Exception();
				$_SESSION["ratatoeskr_uid"]    = $user->get_id();
				$_SESSION["ratatoeskr_pwhash"] = $user->pwhash;
			}
			catch(Exception $e)
			{
				$ste->vars["login_failed"] = True;
			}
			
			/* Login successful. */
			$data["user"] = $user;
			$ste->vars["user"] = array("name" => $user->username);
			throw new Redirect(array("content", "write"));
		}
		
		echo $ste->exectemplate("systemtemplates/backend_login.html");
	}),
	"logout" => url_action_simple(function($data)
	{
		echo "foo";
		unset($_SESSION["ratatoeskr_uid"]);
		unset($_SESSION["ratatoeskr_pwhash"]);
		throw new Redirect(array("login"));
	}),
	"content" => url_action_subactions(array(
		"write" => function(&$data, $url_now, &$url_next)
		{
			global $ste, $translation;
			
			$article = array_slice($url_next, 0);
			$url_next = array();
			
			$ste->vars["section"] = "content";
			$ste->vars["submenu"] = "newarticle";
			
			if(empty($article))
			{
				/* New Article */
				$ste->vars["pagetitle"] = $translation["new_article"];
			}
			
			echo $ste->exectemplate("systemtemplates/content_write.html");
		}
	))
));

?>
