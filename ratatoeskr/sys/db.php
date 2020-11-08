<?php
/*
 * File: ratatoeskr/sys/db.php
 *
 * Helper functions for dealing with MySQL.
 *
 * License:
 * This file is part of Ratatöskr.
 * Ratatöskr is licensed unter the MIT / X11 License.
 * See "ratatoeskr/licenses/ratatoeskr" for more information.
 */

if (!defined("SETUP")) {
    require_once(dirname(__FILE__) . "/../config.php");
}

require_once(dirname(__FILE__) . "/utils.php");
