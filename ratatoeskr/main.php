<?php
/*
 * File: ratatoeskr/main.php
 * Initialize and launch Ratatöskr
 * 
 * License:
 * This file is part of Ratatöskr.
 * Ratatöskr is licensed unter the MIT / X11 License.
 * See "ratatoeskr/licenses/ratatoeskr" for more information.
 */

require_once(dirname(__FILE__) . "/sys/db.php");
require_once(dirname(__FILE__) . "/sys/models.php");
require_once(dirname(__FILE__) . "/sys/init_ste.php");
require_once(dirname(__FILE__) . "/sys/translation.php");
require_once(dirname(__FILE__) . "/sys/urlprocess.php");
require_once(dirname(__FILE__) . "/sys/plugin_api.php");
require_once(dirname(__FILE__) . "/frontend.php");
require_once(dirname(__FILE__) . "/backend.php");

function ratatoeskr()
{
	global $backend_subactions, $ste, $url_handlers;
	session_start();
	if(!CONFIG_FILLED_OUT)
		return setup();
	
	db_connect();
	
	$activeplugins = array_filter(PluginDB::all(), function($plugin) { return $plugin->active; });
	$plugin_objs = array();
	foreach($activeplugins as $plugin)
	{
		eval($plugin->phpcode);
		$plugin_obj = new $plugin->class;
		$plugin_obj->init();
		$plugin_objs[] = $plugin_obj;
	}
	
	/* Register URL handlers */
	register_url_handler("_default", "frontend_url_handler");
	register_url_handler("_index", "frontend_url_handler");
	register_url_handler("index", "frontend_url_handler");
	register_url_handler("backend", $backend_subactions);
	register_url_handler("_notfound", url_action_simple(function($data)
	{
		global $ste;
		//header("HTTP/1.1 404 Not Found");
		$ste->vars["title"]   = "404 Not Found";
		$ste->vars["details"] = str_replace("[[URL]]", $_SERVER["REQUEST_URI"], (isset($translation) ? $translation["e404_details"] : "The page [[URL]] could not be found. Sorry."));
		echo $ste->exectemplate("systemtemplates/error.html");
	}));
	
	$urlpath = explode("/", $_GET["action"]);
	$rel_path_to_root = implode("/", array_merge(array("."), array_repeat("..", count($urlpath) - 1)));
	$GLOBALS["rel_path_to_root"] = $rel_path_to_root;
	$data = array("rel_path_to_root" => $rel_path_to_root);
	$ste->vars["rel_path_to_root"] = $rel_path_to_root;
	
	url_process($urlpath, $url_handlers, $data);
}

?>
