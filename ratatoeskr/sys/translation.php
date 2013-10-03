<?php
/*
 * File: ratatoeskr/sys/translation.php
 * Load translation.
 * 
 * License:
 * This file is part of Ratatöskr.
 * Ratatöskr is licensed unter the MIT / X11 License.
 * See "ratatoeskr/licenses/ratatoeskr" for more information.
 */

require_once(dirname(__FILE__) . "/utils.php");
require_once(dirname(__FILE__) . "/init_ste.php");

if(!defined("SETUP"))
	require_once(dirname(__FILE__) . "/models.php");

if(!defined("TRANSLATION_PLUGIN_LOADED"))
{
	$ste->register_tag(
		"get_translation",
		function($ste, $params, $sub)
		{
			global $translation;
			if((!isset($translation)) or empty($params["for"]) or (!isset($translation[$params["for"]])))
				return "";
			$rv = $translation[$params["for"]];
			return (!empty($params["raw"])) ? $rv : htmlesc($rv);
		}
	);
	define("TRANSLATION_PLUGIN_LOADED", True);
}

/*
 * Function: load_language
 * Load a language (i.e. set the global $translation variable).
 * 
 * Parameters:
 * 	$lang - The language (2-Letter code, e.g. "en", "de", "it" ...) to load. NULL for default (from database).
 */
function load_language($lang=NULL)
{
	if(!defined("SETUP"))
	{
		global $ratatoeskr_settings;
		if($lang === NULL)
			$lang = $ratatoeskr_settings["default_language"];
	}
	else
	{
		if($lang === NULL)
			$lang = "en";
	}
	
	/*
	 * Because we will include an file defined by the $lang param, we will
	 * only allow alphabetic characters, so this function should not be
	 * vulnerable to LFI-Exploits...
	 */
	$lang = implode("", array_filter(str_split($lang, 1), "ctype_alpha"));
	
	require(dirname(__FILE__) . "/../translations/$lang.php");
	
	$GLOBALS["translation"] = $translation;
}

?>
