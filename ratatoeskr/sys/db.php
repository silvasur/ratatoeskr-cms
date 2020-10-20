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
