<?php
/*
 * File: urlprocess.php
 * 
 * Providing functions / classes to handle URLs
 *
 * This file is part of Ratatöskr.
 * Ratatöskr is licensed unter the MIT / X11 License.
 * See "ratatoeskr/licenses/ratatoeskr" for more information.
 */

class Redirect extends Exception
{
	public $nextpath;
	public function __construct($nextpath)
	{
		$this->nextpath = $nextpath;
		parent::__construct();
	}
}

class NotFoundError extends Exception { }

function url_action_simple($function)
{
	return function(&$data, $url_now, &$url_next) use ($function)
	{
		try
		{
			$data = call_user_func($function, $data);
			$url_next = array();
		}
		catch(Redirect $e)
		{
			$url_next = $e->nextpath;
		}
	};
}

function url_action_subactions($actions)
{
	return function (&$data, $url_now, &$url_next) use ($actions)
	{
		$result = url_process($url_next, $actions, $data);
		if($result !== NULL)
			$url_next = $result;
	};
}

function url_process($url, $actions, &$data)
{
	$epilog_running = 0;
	if(is_string($url))
		$url = explode("/", $url);
	if(count($url) == 0)
		$url = array("_index");
	
	if(isset($actions["_prelude"]))
		$url = array_merge(array("_prelude"), $url);
	
	$url_now  = $url[0];
	$url_next = array_slice($url, 1);
	
	while(is_string($url_now) and ($url_now != "") and ($url_now != ".."))
	{
		$cb = NULL;
		if(isset($actions[$url_now]))
			$cb = $actions[$url_now];
		else if(isset($actions["_default"]))
			$cb = $actions["_default"];
		else if(isset($actions["_notfound"]))
			$cb = $actions["_notfound"];
		else
			throw new NotFoundError();
		
		try
		{
			$cb($data, $url_now, $url_next);
		}
		catch(NotFoundError $e)
		{
			if(isset($actions["_notfound"]))
				$url_next = array("_notfound");
			else
				throw $e;
		}
		
		if(count($url_next) > 0)
		{
			$url_now  = $url_next[0];
			$url_next = array_slice($url_next, 1);
		}
		else if(isset($actions["_epilog"]) and ($epilog_running <= 0))
		{
			$epilog_running = 2;
			$url_now        = "_epilog";
		}
		else
			$url_now = "";
		
		--$epilog_running;
	}
	
	if($url_now == "..")
		return $url_next;
	else
		return NULL;
}

?>
