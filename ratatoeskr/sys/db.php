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

use r7r\cms\sys\Database;
use r7r\cms\sys\Env;

if (!defined("SETUP")) {
    require_once(dirname(__FILE__) . "/../config.php");
}

require_once(dirname(__FILE__) . "/utils.php");

/**
 * Prepares statement (1st argument) like {@see Database::prepStmt()} and executes it with the remaining arguments.
 *
 * @param mixed ...$args
 * @return PDOStatement
 *
 * @deprecated Use {@see Database::query()} instead.
 */
function qdb(...$args): PDOStatement
{
    if (count($args) < 1) {
        throw new InvalidArgumentException("qdb needs at least 1 argument");
    }

    return Env::getGlobal()->database()->query((string)$args[0], ...array_slice($args, 1));
}
