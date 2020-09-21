<?php

// Use this as the router script with php -S

if (php_sapi_name() !== 'cli-server') {
    die("Only for use in 'cli-server' SAPI");
}

// Enable some development flags
define("DEV_SKIP_APACHE_CHK", true);

[$_cliserver_path, $_cliserver_query] = array_pad(explode("?", $_SERVER["REQUEST_URI"], 2), 2, "");

if (!empty($_cliserver_query)) {
    parse_str($_cliserver_query, $_GET);
}
unset($_cliserver_query);

if (!file_exists(__DIR__ . $_cliserver_path)) {
    $_GET['action'] = ltrim($_cliserver_path, "/");
    unset($_cliserver_path);

    require_once(__DIR__ . "/ratatoeskr/main.php");
    ratatoeskr();
    exit;
}

if (preg_match('/\.php$/', $_cliserver_path)) {
    require_once(__DIR__ . $_cliserver_path);
    exit;
}

// Serve as-is
return false;
