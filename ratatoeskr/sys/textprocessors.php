<?php
/*
 * File: ratatoeskr/sys/textprocessors.php
 * Manage text processors (functions that transform text to HTML) and implement some default ones.
 *
 * License:
 * This file is part of Ratatöskr.
 * Ratatöskr is licensed unter the MIT / X11 License.
 * See "ratatoeskr/licenses/ratatoeskr" for more information.
 */

require_once(dirname(__FILE__) . "/../libs/markdown.php");
require_once(dirname(__FILE__) . "/utils.php");

/*
 * Function: textprocessor_register
 * Register a textprocessor.
 *
 * Parameters:
 *  $name               - The name of the textprocessor
 *  $fx                 - The textprocessor function (function($input), returns HTML)
 *  $visible_in_backend - Should this textprocessor be visible in the backend? Defaults to True.
 */
function textprocessor_register($name, $fx, $visible_in_backend=true)
{
    global $textprocessors;
    $textprocessors[$name] = [$fx, $visible_in_backend];
}

/*
 * Function: textprocessor_apply
 * Apply a textprocessor on a text.
 *
 * Parameters:
 *  $text          - The input text.
 *  $textprocessor - The name of the textprocessor.
 *
 * Returns:
 *  HTML
 */
function textprocessor_apply($text, $textprocessor)
{
    global $textprocessors;
    if (!isset($textprocessors[$textprocessor])) {
        throw new Exception("Unknown Textprocessor: $textprocessor");
    }

    $fx = @$textprocessors[$textprocessor][0];
    if (!is_callable($fx)) {
        throw new Exception("Invalid Textprocessor: $textprocessor");
    }

    return call_user_func($fx, $text);
}

/*
 * Function: textprocessor_apply_translation
 * Applys a textprocessor automatically on a <Translation> object. The used textprocessor is determined by the $texttype property.
 *
 * Parameters:
 *  $translationobj - The <Translation> object.
 *
 * Returns:
 *  HTML
 */
function textprocessor_apply_translation($translationobj)
{
    return textprocessor_apply($translationobj->text, $translationobj->texttype);
}

if (!isset($textprocessors)) {
    $textprocessors = [
        "Markdown" => ["Markdown", true],
        "Plain Text" => [function ($text) {
            return str_replace(["\r\n", "\n"], ["<br />", "<br />"], htmlesc($text));
        }, true],
        "HTML" => [function ($text) {
            return $text;
        }, true]
    ];
}
