<?php
/*
 * File: ratatoeskr/main.php
 * Initialize and launch Ratatöskr
 *
 * License:
 * This file is part of Ratatöskr.
 * Ratatöskr is licensed unter the MIT / X11 License.
 * See "ratatoeskr/licenses/ratatoeskr" for more information.
 */

if (is_file(dirname(__FILE__) . "/config.php")) {
    require_once(dirname(__FILE__) . "/config.php");
}
if (!defined("CONFIG_FILLED_OUT") || !CONFIG_FILLED_OUT) {
    die("Config file not filled out!");
}

require_once(dirname(__FILE__) . "/vendor/autoload.php");
require_once(dirname(__FILE__) . "/sys/db.php");
require_once(dirname(__FILE__) . "/sys/models.php");
require_once(dirname(__FILE__) . "/sys/init_ste.php");
require_once(dirname(__FILE__) . "/sys/translation.php");
require_once(dirname(__FILE__) . "/sys/urlprocess.php");
require_once(dirname(__FILE__) . "/sys/plugin_api.php");
require_once(dirname(__FILE__) . "/frontend.php");
require_once(dirname(__FILE__) . "/backend.php");

$plugin_objs = [];

function ratatoeskr()
{
    global $ste;
    try {
        _ratatoeskr();
    } catch (Exception $e) {
        header("HTTP/1.1 500 Internal Server Error");
        $ste->vars["title"] = "500 Internal Server Error";
        if (__DEBUG__) {
            $ste->vars["details"] = $e->__toString();
        }
        echo $ste->exectemplate("/systemtemplates/error.html");
    }
}

function _ratatoeskr()
{
    global $backend_subactions, $ste, $url_handlers, $ratatoeskr_settings, $plugin_objs, $api_compat;

    session_start();
    clean_database();

    if (isset($ratatoeskr_settings["debugmode"]) and $ratatoeskr_settings["debugmode"]) {
        define("__DEBUG__", true);
    }

    if (PLUGINS_ENABLED) {
        $activeplugins = array_filter(Plugin::all(), function ($plugin) {
            return $plugin->active;
        });
        /** @var Plugin $plugin */
        foreach ($activeplugins as $plugin) {
            if (!in_array($plugin->api, $api_compat)) {
                $plugin->active = false;
                $plugin->save();
                continue;
            }

            eval($plugin->code);
            /** @var RatatoeskrPlugin $plugin_obj */
            $plugin_obj = new $plugin->classname($plugin->get_id());
            if ($plugin->update) {
                $plugin_obj->update();
                $plugin->update = false;
                $plugin->save();
            }
            $plugin_obj->init();
            $plugin_objs[$plugin->get_id()] = $plugin_obj;
        }
    }

    /* Register URL handlers */
    build_backend_subactions();
    register_url_handler("_default", "frontend_url_handler");
    register_url_handler("_index", "frontend_url_handler");
    register_url_handler("index", "frontend_url_handler");
    register_url_handler("backend", $backend_subactions);
    register_url_handler("_notfound", url_action_simple(function ($data) {
        global $ste;
        header("HTTP/1.1 404 Not Found");
        $ste->vars["title"]   = "404 Not Found";
        $ste->vars["details"] = str_replace("[[URL]]", $_SERVER["REQUEST_URI"], (isset($translation) ? $translation["e404_details"] : "The page [[URL]] could not be found. Sorry."));
        echo $ste->exectemplate("/systemtemplates/error.html");
    }));

    $urlpath = explode("/", @$_GET["action"]);
    $rel_path_to_root = implode("/", array_merge(["."], array_repeat("..", count($urlpath) - 1)));
    $GLOBALS["rel_path_to_root"] = $rel_path_to_root;
    $data = ["rel_path_to_root" => $rel_path_to_root];
    $ste->vars["rel_path_to_root"] = $rel_path_to_root;

    url_process($urlpath, $url_handlers, $data);

    if (PLUGINS_ENABLED) {
        foreach ($plugin_objs as $plugin_obj) {
            $plugin_obj->atexit();
        }
    }
    $ratatoeskr_settings->save();
}
