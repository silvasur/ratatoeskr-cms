<?php

/*
 * File: ratatoeskr/sys/init_ste.php
 * 
 * When included, the file will initialize the global STECore instance.
 * 
 * License:
 * This file is part of Ratatöskr.
 * Ratatöskr is licensed unter the MIT / X11 License.
 * See "ratatoeskr/licenses/ratatoeskr" for more information.
 */

require_once(dirname(__FILE__) . "/../libs/stupid_template_engine.php");

$tpl_basedir = dirname(__FILE__) . "/../templates";

if(!isset($ste))
{
	/*
	 * Variable: $ste
	 * 
	 * The global STECore (Stupid Template Engine) instance.
	 */
	$ste = new \ste\STECore(new \ste\FilesystemStorageAccess("$tpl_basedir/src", "$tpl_basedir/transc"));
}

$ste->register_tag(
	"l10n_replace",
	function($ste, $params, $sub)
	{
		$content = $sub($ste);
		foreach($params as $name => $replace)
			$content = str_replace("[[$name]]", $replace, $content);
		return $content;
	}
);
$ste->register_tag(
	"capitalize",
	function($ste, $params, $sub)
	{
		return ucwords($sub($ste));
	}
);

?>
