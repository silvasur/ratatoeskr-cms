<?php
/*
 * File: plugin_api.php
 * 
 * Plugin API contains the plugin base class and other interfaces to Ratatöskr.
 *
 * This file is part of Ratatöskr.
 * Ratatöskr is licensed unter the MIT / X11 License.
 * See "ratatoeskr/licenses/ratatoeskr" for more information.
 */

require_once(dirname(__FILE__) . "/db.php");


abstract class RatatoeskrPlugin
{
	private $id;
	protected $kvstorage;
	protected $smarty;
	
	public function __construct($id)
	{
		global $smarty;
		$this->id        = $id;
		$this->kvstorage = new PluginKVStorage($id);
		$this->smarty    = $smarty;
	}
	
	public function get_id() { return $this->id; }
	
	public function init() {}
	public function install() {}
	public function uninstall() {}
}

?>
