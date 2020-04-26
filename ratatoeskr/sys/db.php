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

$db_con = null;

/*
 * Function: db_connect
 *
 * Establish a connection to the MySQL database.
 */
function db_connect()
{
    global $config;
    global $db_con;

    $db_con = new PDO(
        "mysql:host=" . $config["mysql"]["server"] . ";dbname=" . $config["mysql"]["db"] . ";charset=utf8",
        $config["mysql"]["user"],
        $config["mysql"]["passwd"],
        [
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
        ]
    );
    $db_con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}

/*
 * Function: sub_prefix
 *
 * Substitutes "PREFIX_" in the input string with the prefix from the config.
 */
function sub_prefix($q)
{
    global $config;
    return str_replace("PREFIX_", $config["mysql"]["prefix"], $q);
}

/*
 * Function: prep_stmt
 *
 * Prepares a SQL statement using the global DB connection.
 * This will also replace "PREFIX_" with the prefix defined in 'config.php'.
 *
 * Parameters:
 *  $q - The query / statement to prepare.
 *
 * Returns:
 *  A PDOStatement object.
 */
function prep_stmt($q)
{
    global $db_con;

    return $db_con->prepare(sub_prefix($q));
}

/*
 * Function: qdb
 *
 * Prepares statement (1st argument) with <prep_stmt> and executes it with the remaining arguments.
 *
 * Returns:
 *  A PDOStatement object.
 */
function qdb()
{
    $args = func_get_args();
    if (count($args) < 1) {
        throw new InvalidArgumentException("qdb needs at least 1 argument");
    }

    $stmt = prep_stmt($args[0]);
    $stmt->execute(array_slice($args, 1));
    return $stmt;
}

/*
 * Class: Transaction
 *
 * Makes using transactions easier.
 */
class Transaction
{
    public $startedhere;

    /*
     * Constructor: __construct
     *
     * Start a new transaction.
     */
    public function __construct()
    {
        global $db_con;
        $this->startedhere = !($db_con->inTransaction());
        if ($this->startedhere) {
            $db_con->beginTransaction();
        }
    }

    /*
     * Function: commit
     *
     * Commit the transaction.
     */
    public function commit()
    {
        global $db_con;

        if ($this->startedhere) {
            $db_con->commit();
        }
    }

    /*
     * Function: rollback
     *
     * Toll the transaction back.
     */
    public function rollback()
    {
        global $db_con;

        if ($this->startedhere) {
            $db_con->rollBack();
        }
    }
}
