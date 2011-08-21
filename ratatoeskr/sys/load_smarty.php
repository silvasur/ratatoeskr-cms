<?php
/*
 * File: ratatoeskr/sys/load_smarty.php
 * 
 * Create the global Smarty instance.
 * 
 * License:
 * This file is part of Ratatöskr.
 * Ratatöskr is licensed unter the MIT / X11 License.
 * See "ratatoeskr/licenses/ratatoeskr" for more information.
 */

require_once(dirname(__FILE__) . "/../libs/smarty/Smarty.class.php");

if(!isset($smarty))
{
	/*
	 * Variable: $smarty
	 * Global smarty instance.
	 */
	$smarty = new Smarty();
	$smarty->setTemplateDir(dirname(__FILE__) . "/../templates/");
	$smarty->setCompileDir(dirname(__FILE__) . "/../tmp/smartytemplates_c");
	$smarty->setCacheDir(dirname(__FILE__) . "/../tmp/smarty/cache");
	$smarty->setConfigDir(dirname(__FILE__) . "/../smarty_confdir");
}

?>
