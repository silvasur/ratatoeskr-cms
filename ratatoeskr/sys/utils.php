<?php
/*
 * File: ratatoeskr/sys/utils.php
 *
 * Various useful helper functions.
 *
 * License:
 * This file is part of Ratatöskr.
 * Ratatöskr is licensed unter the MIT / X11 License.
 * See "ratatoeskr/licenses/ratatoeskr" for more information.
 */

/*
 * Function: delete_directory
 * Delete a directory and all of its content.
 */
function delete_directory($dir)
{
    $dir_content = scandir($dir);
    foreach ($dir_content as $f) {
        if (($f == "..") or ($f == ".")) {
            continue;
        }

        $f = "$dir/$f";

        if (is_dir($f)) {
            delete_directory($f);
        } else {
            unlink($f);
        }
    }
    rmdir($dir);
}

/*
 * Constant: SITE_BASE_PATH
 * The Base path of this ratatoeskr site.
 */
define("SITE_BASE_PATH", dirname(dirname(dirname(__FILE__))));
