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

require_once(dirname(__FILE__) . "/models.php");

/*
 * Class: RatatoeskrPlugin
 * An abstract class to be extended in order to write your own Plugin.
 */
abstract class RatatoeskrPlugin
{
	private $id;
	
	/*
	 * Variables: Protected variables
	 *
	 * $kvstorage - The Key-Value-Storage for the Plugin.
	 * $smarty - Access to the global smarty object.
	 */
	protected $kvstorage;
	protected $smarty;
	
	
	/*
	 * Constructor: __construct
	 * Performing some neccessary initialisation stuff.
	 * If you are overwriting this function, you *really* should call parent::__construct!
	 * 
	 * Parameters:
	 * 	$id - The ID of the plugin (not the name).
	 */
	public function __construct($id)
	{
		global $smarty;
		$this->id        = $id;
		
		$this->kvstorage = new PluginKVStorage($id);
		$this->smarty    = $smarty;
	}
	
	/*
	 * Functions: Some getters
	 * 
	 * get_id - get the Plugin-ID
	 * get_additional_files_dir - Path to directory with the additional files
	 * get_template_dir - Path to template directory
	 */
	final public function get_id() { return $this->id; }
	final protected function get_additional_files_dir() { return dirname(dirname(__FILE__)) . "/plugin_extradata/" . $this->id; }
	final protected function get_template_dir() { return dirname(dirname(__FILE__)) . "/templates/plugintemplates/" . $this->id; }
	
	/*
	 * Function: register_url_handler
	 * Easy way for register a URL handler
	 * 
	 * Parameters:
	 * 	$name - Name of URL
	 * 	$objfunction - Name of object function.
	 */
	final protected function register_url_handler($name, $objfunction)
	{
		register_url_handler($name, array($this, $objfunction));
	}
	
	final protected function register_settings_page($get, $validate, $set, $structure)
	
	/*
	 * Functions: Functions that are called at special events
	 * 
	 * init - Will be called after plugin is loaded. You should register your stuff here.
	 * install - Will be called after installation. If your plugin needs to initialize some database stuff or generate files, this is the right function.
	 * uninstall - Will be called during uninstallation. If you used the install function you should undo your custom installation stuff.
	 */
	public function init() {}
	public function install() {}
	public function uninstall() {}
}

$url_handlers = array();
/*
 * Function: register_url_handler
 * Register an URL handler. See <urlprocess.php> for more details.
 * 
 * Parameters:
 * 	$name - The name of the new URL
 * 	$callback - The Function to be called (see <url_process>).
 */
function register_url_handler($name, $callback)
{
	global $url_handlers;
	$url_handlers[$name] = $callback;
}

?>
