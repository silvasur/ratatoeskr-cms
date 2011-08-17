<?php

/*require_once(dirname(__FILE__) . "/ratatoeskr/main.php");
ratatoeskr();*/

header("Content-type: text/plain");
print "\$_POST:\n";
print_r($_POST);
print "\n\n\$_GET:\n";
print_r($_GET);
print "\n\n\$_REQUEST:\n";
print_r($_REQUEST);
print "\n\n\$_SERVER:\n";
print_r($_SERVER);
?>
