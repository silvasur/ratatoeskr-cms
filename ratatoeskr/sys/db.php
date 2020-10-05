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
use r7r\cms\sys\DbTransaction;

if (!defined("SETUP")) {
    require_once(dirname(__FILE__) . "/../config.php");
}

require_once(dirname(__FILE__) . "/utils.php");

// The global database connection.
// It's usage is deprecated, use the Database object supplied by Env::database() instead.
/** @var PDO|null $db_con */
$db_con = null;

/**
 * Establish the global connection to the MySQL database.
 * This sets the global {@see $db_con}.
 *
 * @deprecated Use the {@see Database} object supplied by {@see Env::database()} instead.
 */
function db_connect(): void
{
    global $db_con;

    $db_con = Env::getGlobal()->database()->getPdo();
}

/**
 * Substitutes "PREFIX_" in the input string with the prefix from the config.
 *
 * @param mixed|string $q
 * @return string
 * @deprecated Use {@see Database::subPrefix()} instead.
 */
function sub_prefix($q): string
{
    return Env::getGlobal()->database()->subPrefix((string)$q);
}

/**
 * Prepares a SQL statement using the global DB connection.
 * This will also replace "PREFIX_" with the prefix defined in 'config.php'.
 *
 * @param mixed|string $q The query / statement to prepare.
 * @return PDOStatement
 *
 * @deprecated Use {@see Database::prepStmt()} instead.
 */
function prep_stmt($q): PDOStatement
{
    return Env::getGlobal()->database()->prepStmt((string)$q);
}

/**
 * Prepares statement (1st argument) with {@see prep_stmt()} and executes it with the remaining arguments.
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

/**
 * Makes using transactions easier.
 *
 * @deprecated Use {@see DbTransaction} instead.
 */
class Transaction
{
    /** @var DbTransaction */
    private $tx;

    /**
     * Start a new transaction.
     */
    public function __construct()
    {
        $this->tx = new DbTransaction(Env::getGlobal()->database());
    }

    /**
     * Commit the transaction.
     */
    public function commit(): void
    {
        $this->tx->commit();
    }

    /**
     * Roll the transaction back.
     */
    public function rollback(): void
    {
        $this->tx->rollback();
    }
}
