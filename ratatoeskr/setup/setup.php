<?php

use r7r\ste;
use r7r\cms\sys\PasswordHash;

define("SETUP", true);

require_once(dirname(__FILE__) . "/../vendor/autoload.php");
require_once(dirname(__FILE__) . "/../sys/init_ste.php");
require_once(dirname(__FILE__) . "/../sys/translation.php");
require_once(dirname(__FILE__) . "/../languages.php");
require_once(dirname(__FILE__) . "/create_tables.php");

/** @var ste\STECore $ste */
assert(isset($ste));

$rel_path_to_root = ".";
$ste->vars["rel_path_to_root"] = $rel_path_to_root;

$ste->vars["translations"] = [];
foreach ($languages as $langcode => $langinfo) {
    if ($langinfo["translation_exist"]) {
        $ste->vars["translations"][$langcode] = $langinfo["language"];
    }
}

if (isset($_GET["lang"]) and (@$languages[$_GET["lang"]]["translation_exist"])) {
    load_language($_GET["lang"]);

    assert(isset($translation));

    $lang = $_GET["lang"];
    $ste->vars["lang"] = $_GET["lang"];
} else {
    die($ste->exectemplate("/systemtemplates/setup_select_lang.html"));
}

if (isset($_POST["apply_setup"])) {
    if (empty($_POST["admin_username"]) or empty($_POST["admin_init_password"])) {
        $ste->vars["error"] = $translation["admin_data_must_be_filled_out"];
    } else {
        $config["mysql"]["server"] = $_POST["mysql_host"];
        $config["mysql"]["db"]     = $_POST["mysql_database"];
        $config["mysql"]["user"]   = $_POST["mysql_user"];
        $config["mysql"]["passwd"] = $_POST["mysql_password"];
        $config["mysql"]["prefix"] = $_POST["table_prefix"];

        try {
            create_mysql_tables();

            /* Writing some demo data to database */
            require_once(dirname(__FILE__) . "/../sys/models.php");

            $ratatoeskr_settings["default_language"] = $lang;
            $ratatoeskr_settings["comment_visible_defaut"] = true;
            $ratatoeskr_settings["allow_comments_default"] = true;
            $ratatoeskr_settings["comment_textprocessor"] = "Markdown";
            $ratatoeskr_settings["languages"] = $lang == "en" ? ["en"] : [$lang, "en"];
            $ratatoeskr_settings["last_db_cleanup"] = time();
            $ratatoeskr_settings["debugmode"] = false;

            $style = Style::create("default");
            $style->code = <<<STYLE
* {
    font-family: sans-serif;
    font-size: 10pt;
}

html {
    margin: 0px;
    padding: 0px;
}

body {
    margin: 0px;
    padding: 0px;
}

#maincontainer {
    width: 80%;
    margin: 0px auto 0px;
    padding: 0px;
}

#heading {
    text-align: center;
    border-bottom: 1px solid black;
    margin: 0px auto 0px;
    padding: 10mm 3mm 5mm
}

h1 {
    font-size: 24pt;
    font-weight: bold;
    padding: 0px;
    margin: 0px auto 2mm;
}

h2 {
    font-size: 14pt;
    font-weight: bold;
}

h3 {
    font-size: 14pt;
    font-weight: normal;
}

h4 {
    font-size: 12pt;
    font-weight: bold;
}

h5 {
    font-size: 12pt;
    font-weight: normal
}

h6 {
    font-size: 10pt;
    font-weight: bold;
    text-decoration: underline;
}

#mainmenu {
    border-bottom: 1px solid black;
    list-style: none;
    height: 10mm;
    padding: 0px;
    margin: 0px 0px 2mm;
}

#mainmenu li {
    float: left;
    margin: 0px 0px 2mm;
    height: 10mm;
    overflow: hidden;
}

#mainmenu li a {
    color: #444;
    text-decoration: none;
    font-size: 12pt;
    margin: 0px;
    padding: 2mm 7.5mm 0px;
    background: white;
    display: block;
    height: 10mm;
}

#mainmenu li.active a {
    color: black;
    font-weight: bold;
}

#mainmenu li a:hover {
    background: #eee;
    color: #000;
}

#metabar {
    float: right;
    width: 50mm;
    margin: 0px;
    padding: 0px 0px 0px 5mm;
    border-left: 1px solid black;
}

div.metabar_module {
    border-top: 1px solid black;
    padding: 2mm 0px 0px;
    margin: 2mm 0px 0px;
}

div.metabar_module:first-child {
    border-top: none;
    margin: 0px;
    padding: 0px;
}

div.metabar_module h2 {
    font-size: 10pt;
    font-weight: bold;
    padding: 0px;
    margin: 0px 0px 2mm;
}

#content {
    border-right: 1px solid black;
    margin: 0px 55mm 0px 0px;
    padding: 0px 2mm 0px 0px;
}

#footer {
    clear: both;
    margin: 4mm 0mm 4mm;
    padding: 2mm 0mm 0mm;
    text-align: center;
    border-top: 1px solid black;
}

table.listtab {
    border-collapse: collapse;
}
STYLE;
            $style->save();

            $section = Section::create("home");
            $section->title["en"] = new Translation("Home", "");
            if ($lang != "en") {
                $section->title[$lang] = new Translation("Home", "");
            }
            $section->template = "standard.html";
            $section->add_style($style);
            $section->save();

            $ratatoeskr_settings["default_section"] = $section->get_id();

            $ratatoeskr_settings->save();

            $admingrp = Group::create("admins");
            $admin = User::create($_POST["admin_username"], PasswordHash::hash($_POST["admin_init_password"]));
            $admin->save();
            $admingrp->include_user($admin);

            $article = Article::create("congratulations");
            $article->title["en"] = new Translation("Congratulations! You have just installed Ratatöskr!", "");
            $article->text["en"] = new Translation("Congratulations! You have just installed Ratatöskr!", "Markdown");
            $article->excerpt["en"] = new Translation("Congratulations! You have just installed Ratatöskr!", "Markdown");
            $article->status = Article::STATUS_LIVE;
            $article->timestamp = time();
            $article->allow_comments = true;
            $article->set_section($section);
            $article->save();

            /*try {
                Repository::create("http://r7r-repo-community.silvasur.net/");
                Repository::create("http://r7r-repo-official.silvasur.net/");
            } catch (RepositoryUnreachableOrInvalid $e) {
                $ste->vars["notice"] = $translation["could_not_initialize_repos"];
	    }*/

            /* Almost done. Give the user the config file. */
            $ste->vars["config"] = "<?php\n"
                . "\n"
                . "define(\"__DEBUG__\", false);\n"
                . "define(\"CONFIG_FILLED_OUT\", true);\n"
                . "define(\"PLUGINS_ENABLED\", true);\n"
                . "\n"
                . "\$config[\"mysql\"][\"server\"] = '" . addcslashes($config["mysql"]["server"], "'") . "';\n"
                . "\$config[\"mysql\"][\"db\"]     = '" . addcslashes($config["mysql"]["db"], "'") . "';\n"
                . "\$config[\"mysql\"][\"user\"]   = '" . addcslashes($config["mysql"]["user"], "'") . "';\n"
                . "\$config[\"mysql\"][\"passwd\"] = '" . addcslashes($config["mysql"]["passwd"], "'") . "';\n"
                . "\$config[\"mysql\"][\"prefix\"] = '" . addcslashes($config["mysql"]["prefix"], "'") . "';";
            die($ste->exectemplate("/systemtemplates/setup_done.html"));
        } catch (MySQLException $e) {
            $ste->vars["error"] = $e->getMessage();
        }
    }
}

echo $ste->exectemplate("/systemtemplates/setup_dbsetup.html");
