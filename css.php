<?php
/*
 * File: css.php
 * Spit out User-defined CSS styles.
 *
 * License:
 * This file is part of Ratatöskr.
 * Ratatöskr is licensed unter the MIT / X11 License.
 * See "ratatoeskr/licenses/ratatoeskr" for more information.
 */

require_once(dirname(__FILE__) . "/ratatoeskr/sys/models.php");

if(!isset($_GET["name"]))
    die();
try
{
    $style = Style::by_name($_GET["name"]);
    header("Content-Type: text/css; charset=UTF-8");
    echo str_replace("%root%", ".", $style->code);
}
catch(DoesNotExistError $e)
{
    header("HTTP/1.1 404 Not Found");
    header("Content-Type: text/plain; charset=UTF-8");
    echo "404 - Not found.";
}

?>
