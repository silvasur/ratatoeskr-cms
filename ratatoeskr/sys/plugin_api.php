<?php
/*
 * File: ratatoeskr/sys/plugin_api.php
 * Plugin API contains the plugin base class and other interfaces to Ratatöskr.
 * 
 * License:
 * This file is part of Ratatöskr.
 * Ratatöskr is licensed unter the MIT / X11 License.
 * See "ratatoeskr/licenses/ratatoeskr" for more information.
 */

require_once(dirname(__FILE__) . "/models.php");
require_once(dirname(__FILE__) . "/textprocessors.php");
require_once(dirname(__FILE__) . "/../frontend.php");

/*
 * Constant: APIVERSION
 * The current API version (1).
 */
define("APIVERSION", 1);

/*
 * Array: $api_compat
 * Array of API versions, this version is compatible to (including itself).
 */
$api_compat = array(1);

$url_handlers = array();
/*
 * Function: register_url_handler
 * Register an URL handler. See <ratatoeskr/sys/urlprocess.php> for more details.
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

$pluginpages_handlers = array();

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
	 * $ste - Access to the global STECore object.
	 * $rel_path_to_root - Relative URL to the root of the page.
	 */
	protected $kvstorage;
	protected $ste;
	protected $rel_path_to_root;
	
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
		global $ste, $rel_path_to_root;
		$this->id        = $id;
		
		$this->kvstorage        = new PluginKVStorage($id);
		$this->ste              = $ste;
		$this->rel_path_to_root = $rel_path_to_root;
	}
	
	/*
	 * Functions: Some getters
	 * 
	 * get_id - get the Plugin-ID
	 * get_custompriv_dir - Get path to the custompriv directory of your plugin.
	 * get_custompub_dir - Get path to the custompub directory of your plugin.
	 * get_custompub_url - Get URL (can be accessed from the web) to the custompub directory of your plugin.
	 * get_template_dir - Get path to your template directory to be used with STE.
	 */
	final public function    get_id()             { return $this->id;                                                                         }
	final protected function get_custompriv_dir() { return SITE_BASE_PATH . "/ratatoeskr/plugin_extradata/private/" . $this->id;              }
	final protected function get_custompub_dir()  { return SITE_BASE_PATH . "/ratatoeskr/plugin_extradata/public/" . $this->id;               }
	final protected function get_custompub_url()  { return $GLOBALS["rel_path_to_root"] . "/ratatoeskr/plugin_extradata/public/" . $this->id; }
	final protected function get_template_dir()   { return "/plugintemplates/" . $this->id;                                                   }
	
	/*
	 * Function: register_url_handler
	 * Register a URL handler
	 * 
	 * Parameters:
	 * 	$name - Name of URL
	 * 	$fx   - The function.
	 */
	final protected function register_url_handler($name, $fx)
	{
		register_url_handler($name, $fx);
	}
	
	/*
	 * Function: register_ste_tag
	 * Register a custom STE tag.
	 * 
	 * Parameters:
	 * 	$name - Name of your new STE tag.
	 *	$fx   - Function to register with this tag.
	 */
	final protected function register_ste_tag($name, $fx)
	{
		$this->ste->register_tag($name, $fx);
	}
	
	/*
	 * Function: register_textprocessor
	 * Register a textprocessor.
	 * 
	 * Parameters:
	 * 	$name               - The name of the textprocessor-
	 * 	$fx                 - Function to register (function($input), returns HTML).
	 * 	$visible_in_backend - Should this textprocessor be visible in the backend? Defaults to True.
	 */
	final protected function register_textprocessor($name, $fx, $visible_in_backend=True)
	{
		textprocessor_register($name, $fx, $visible_in_backend);
	}
	
	/*
	 * Function: register_comment_validator
	 * Register a comment validator.
	 * 
	 * A comment validator is a function, that checks the $_POST fields and decides whether a comment should be stored or not (throws an <CommentRejected> exception with the rejection reason as the message).
	 * 
	 * Parameters:
	 * 	$fx - Validator function.
	 */
	final protected function register_comment_validator($fx)
	{
		register_comment_validator($fx);
	}
	
	/*
	 * Function: register_backend_pluginpage
	 * Register a backend subpage for your plugin.
	 * 
	 * Parameters:
	 * 	$label - The label for the page.
	 * 	$fx    - A function for <urlprocess>.
	 * 
	 * Your $fx should output output the result of a STE template, which should load "/systemtemplates/master.html" and overwrite the "content" section.
	 * 
	 * See also:
	 * 	<prepare_backend_pluginpage>
	 */
	final protected function register_backend_pluginpage($label, $fx)
	{
		global $pluginpages_handlers;
		
		$this->ste->vars["pluginpages"][$this->id] = $label;
		sort($this->ste->vars["pluginpages"]);
		$pluginpages_handlers["p{$this->id}"] = $fx;
	}
	
	/*
	 * Function: prepare_backend_pluginpage
	 * Automatically sets the page title and highlights the menu-entry of your backend subpage.
	 */
	final protected function prepare_backend_pluginpage()
	{
		$this->ste->vars["section"]   = "plugins";
		$this->ste->vars["submenu"]   = "plugin" . $this->id;
		$this->ste->vars["pagetitle"] = $this->ste->vars["pluginpages"][$this->id];
	}
	
	/*
	 * Functions: Functions that are called at special events
	 * 
	 * init - Will be called after plugin is loaded. You should register your stuff here.
	 * install - Will be called after installation. If your plugin needs to initialize some database stuff or generate files, this is the right function.
	 * uninstall - Will be called during uninstallation. If you used the install function you should undo your custom installation stuff.
	 * update - Will be called after your plugin was updated to a new version.
	 */
	public function init() {}
	public function install() {}
	public function uninstall() {}
	public function update() {}
}

?>
