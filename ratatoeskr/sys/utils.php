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
 * Function: array_repeat
 *
 * Parameters:
 *
 *  $val -
 *  $n   -
 *
 * Returns:
 *
 *  An array with $val $n-times repeated.
 */

use r7r\cms\sys\Esc;

function array_repeat($val, $n)
{
    $rv = [];
    for ($i = 0; $i < $n; ++$i) {
        array_push($rv, $val);
    }
    return $rv;
}

/*
 * Function: intcmp
 * Compare integers (equavilent to strcmp)
 */
function intcmp($a, $b)
{
    return ($a == $b) ? 0 : (($a < $b) ? -1 : 1);
}

/**
 * Escape HTML (shorter than htmlspecialchars)
 *
 * @param mixed $text Input text
 * @return string HTML
 * @deprecated Use {@see Esc::esc()} instead.
 */
function htmlesc($text): string
{
    return Esc::esc($text);
}

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
