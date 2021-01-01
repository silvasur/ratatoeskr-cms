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

use r7r\cms\sys\Esc;

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
