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

if(!defined("SETUP"))
	require_once(dirname(__FILE__) . "/../config.php");

require_once(dirname(__FILE__) . "/utils.php");

$db_con = Null;

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
		array(
			PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
		));
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
 * 	$q - The query / statement to prepare.
 * 
 * Returns:
 * 	A PDOStatement object.
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
 * 	A PDOStatement object.
 */
function qdb()
{
	$args = func_get_args();
	if(count($args) < 1)
		throw new InvalidArgumentException("qdb needs at least 1 argument");
	
	$stmt = prep_stmt($args[0]);
	$stmt->execute(array_slice($args, 1));
	return $stmt;
}

/*
 * Function: transaction
 * 
 * Executes function $f and wraps it in a transaction.
 * If $f has thrown an exception, the transactrion will be rolled back and the excetion will be re-thrown.
 * Otherwise the transaction will be committed.
 * 
 * Parameters:
 * 	$f - A function / callback.
 */
function transaction($f)
{
	global $db_con;
	
	if($db_con->inTransaction())
		call_user_func($f);
	else
	{
		try
		{
			$db_con->beginTransaction();
			call_user_func($f);
			$db_con->commit();
		}
		catch(Exception $e)
		{
			$db_con->rollBack();
			throw $e;
		}
	}
}

?>
