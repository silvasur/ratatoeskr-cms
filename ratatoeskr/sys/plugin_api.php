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

use r7r\ste\STECore;
use r7r\cms\sys\Env;
use r7r\cms\sys\textprocessors\LegacyTextprocessor;

require_once(dirname(__FILE__) . "/../config.php");
require_once(dirname(__FILE__) . "/models.php");
require_once(dirname(__FILE__) . "/textprocessors.php");
require_once(dirname(__FILE__) . "/../frontend.php");

/**
 * The current API version (6).
 */
define("APIVERSION", 6);

/**
 * @var int[] Array of API versions, this version is compatible to (including itself).
 */
$api_compat = [3, 4, 5, 6];

$url_handlers = []; /* master URL handler */

/**
 * Register an URL handler. See ratatoeskr/sys/urlprocess.php for more details.
 *
 * @param string $name The name of the new URL
 * @param callable $callback The Function to be called (see <url_process>).
 */
function register_url_handler($name, callable $callback)
{
    global $url_handlers;
    $url_handlers[$name] = $callback;
}

$pluginpages_handlers = [];

$articleeditor_plugins = [];

/**
 * An abstract class to be extended in order to write your own Plugin.
 */
abstract class RatatoeskrPlugin
{
    private $id;

    /** @var Env */
    private $env;

    /** @var PluginKVStorage The Key-Value-Storage for the Plugin */
    protected $kvstorage;

    /** @var STECore Access to the global STECore object */
    protected $ste;

    /** @var string Relative URL to the root of the page */
    protected $rel_path_to_root;

    /**
     * Performing some neccessary initialisation stuff.
     * If you are overwriting this function, you *really* should call parent::__construct!
     *
     * @param int $id - The ID of the plugin (not the name).
     */
    public function __construct($id)
    {
        global $ste, $rel_path_to_root;
        $this->id        = $id;

        $this->env = Env::getGlobal();
        $this->kvstorage        = new PluginKVStorage($id);
        $this->ste              = $ste;
        $this->rel_path_to_root = $rel_path_to_root;
    }

    /**
     * Get the Plugin-ID
     * @return int
     */
    final public function get_id()
    {
        return $this->id;
    }

    /**
     * Get path to the custompriv directory of your plugin.
     * @return string
     */
    final protected function get_custompriv_dir()
    {
        return $this->env->siteBasePath() . "/ratatoeskr/plugin_extradata/private/" . $this->id;
    }

    /**
     * Get path to the custompub directory of your plugin.
     * @return string
     */
    final protected function get_custompub_dir()
    {
        return $this->env->siteBasePath() . "/ratatoeskr/plugin_extradata/public/" . $this->id;
    }

    /**
     * Get URL (can be accessed from the web) to the custompub directory of your plugin.
     * @return string
     */
    final protected function get_custompub_url()
    {
        return $GLOBALS["rel_path_to_root"] . "/ratatoeskr/plugin_extradata/public/" . $this->id;
    }

    /**
     * Get path to your template directory to be used with STE.
     * @return string
     */
    final protected function get_template_dir()
    {
        return "/plugintemplates/" . $this->id;
    }

    /**
     * Register a URL handler
     *
     * @param string $name Name of URL
     * @param callable $fx The function.
     */
    final protected function register_url_handler($name, $fx)
    {
        register_url_handler($name, $fx);
    }

    /**
     * Register a custom STE tag.
     *
     * @param string $name Name of your new STE tag.
     * @param callable $fx Function to register with this tag.
     */
    final protected function register_ste_tag($name, $fx)
    {
        $this->ste->register_tag($name, $fx);
    }

    /**
     * Register a textprocessor.
     *
     * @param string $name The name of the textprocessor-
     * @param callable $fx Function to register (function($input), returns HTML).
     * @param bool $visible_in_backend Should this textprocessor be visible in the backend? Defaults to True.
     */
    final protected function register_textprocessor($name, $fx, $visible_in_backend=true)
    {
        $this->env->textprocessors()->register($name, new LegacyTextprocessor($fx, (bool)$visible_in_backend));
    }

    /**
     * Register a comment validator.
     *
     * A comment validator is a function, that checks the $_POST fields and decides whether a comment should be stored
     * or not (throws an (@see CommentRejected} exception with the rejection reason as the message).
     *
     * @param callable $fx Validator function.
     */
    final protected function register_comment_validator($fx)
    {
        global $comment_validators;
        $comment_validators[] = $fx;
    }

    /**
     * Register a function that will be called, after a comment was saved.
     *
     * @param callable $fx Function, that accepts one parameter (a {@see Comment} object).
     */
    final protected function register_on_comment_store($fx)
    {
        global $on_comment_store;
        $on_comment_store[] = $fx;
    }

    /**
     * Register a backend subpage for your plugin.
     *
     * Your $fx should output output the result of a STE template, which should
     * load "/systemtemplates/master.html" and overwrite the "content" section.
     *
     * If you need a URL to your pluginpage, you can use {@see RatatoeskrPlugin::get_backend_pluginpage_url()} and the
     * STE variable $rel_path_to_pluginpage.
     *
     * See also {@see RatatoeskrPlugin::prepare_backend_pluginpage()}
     *
     * @param string $label The label for the page.
     * @param callable $fx A function for <url_process>.
     */
    final protected function register_backend_pluginpage($label, $fx)
    {
        global $pluginpages_handlers;

        $this->ste->vars["pluginpages"][$this->id] = $label;
        asort($this->ste->vars["pluginpages"]);
        $pluginid = $this->id;
        $pluginpages_handlers["p{$this->id}"] = function (&$data, $url_now, &$url_next) use ($pluginid, $fx) {
            global $ste, $rel_path_to_root;
            $ste->vars["rel_path_to_pluginpage"] = "$rel_path_to_root/backend/pluginpages/p$pluginid";
            $rv = call_user_func_array($fx, [&$data, $url_now, &$url_next]);
            unset($ste->vars["rel_path_to_pluginpage"]);
            return $rv;
        };
    }

    /**
     * Register a plugin for the article editor in the backend.
     *
     * You $fx function must take two parameters:
     *
     * - $article:
     *   An {@see Article} object or null, if no Article is edited right now.
     * - $about_to_save:
     *   If true, the article is about to be saved.
     *   If you want to veto the saving, return the rejection reason as a string.
     *   If everything is okay and you need to save additional data, return a callback function that
     *   accepts the saved {@see Article} object (that callback should also write data back to the template, if necessary).
     *   If everything is okay and you do not need to save additional data, return NULL.
     *
     * @param string $label The label for the plugin.
     * @param callable $fx A function that will be called during the articleeditor. See above for a detailed explanation.
     * @param string|null $template The name of the template to display in the editor, relative to your template directory. If you do not want to display anything, you can set ths to NULL.
     */
    final protected function register_articleeditor_plugin($label, $fx, $template)
    {
        global $articleeditor_plugins;

        $articleeditor_plugins[] = [
            "label"    => $label,
            "fx"       => $fx,
            "template" => $this->get_template_dir() . "/" . $template,
            "display"  => $template != null
        ];
    }

    /**
     * Get the URL to your backend plugin page.
     *
     * @return string The URL to your backend plugin page.
     */
    final protected function get_backend_pluginpage_url()
    {
        global $rel_path_to_root;
        return "$rel_path_to_root/backend/pluginpages/p{$this->id}";
    }

    /**
     * Get the {@see ArticleExtradata} object for this plugin and the given article.
     *
     * @param Article $article
     * @return ArticleExtradata
     */
    final protected function get_article_extradata($article)
    {
        return new ArticleExtradata($article->get_id(), $this->id);
    }

    /**
     * Automatically sets the page title and highlights the menu-entry of your backend subpage.
     */
    final protected function prepare_backend_pluginpage()
    {
        $this->ste->vars["section"]   = "plugins";
        $this->ste->vars["submenu"]   = "plugin" . $this->id;
        $this->ste->vars["pagetitle"] = $this->ste->vars["pluginpages"][$this->id];
    }

    /**
     * Will be called after plugin is loaded. You should register your stuff here.
     */
    public function init()
    {
    }

    /**
     * Will be called, when Ratatöskr will exit.
     */
    public function atexit()
    {
    }

    /**
     * Will be called after installation. If your plugin needs to initialize some database stuff or generate files, this is the right function.
     */
    public function install()
    {
    }

    /**
     * Will be called during uninstallation. If you used the install function you should undo your custom installation stuff.
     */
    public function uninstall()
    {
    }

    /**
     * Will be called after your plugin was updated to a new version.
     */
    public function update()
    {
    }
}
