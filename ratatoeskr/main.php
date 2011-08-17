<?php
/*
 * File: main.php
 * Initialize and launch Ratatöskr
 *
 * This file is part of Ratatöskr.
 * Ratatöskr is licensed unter the MIT / X11 License.
 * See "ratatoeskr/licenses/ratatoeskr" for more information.
 */

require_once(dirname(__FILE__) . "/sys/db.php");
require_once(dirname(__FILE__) . "/sys/plugin_api.php");
require_once(dirname(__FILE__) . "/sys/models.php");
require_once(dirname(__FILE__) . "/sys/urlprocess.php");
require_once(dirname(__FILE__) . "/frontend.php");
require_once(dirname(__FILE__) . "/backend.php");

function ratatoeskr()
{
	global $backend_subactions;
	
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
	register_url_handler("backend", $backend_subactions);
	register_url_handler("_notfound", "e404handler");
}

?>
