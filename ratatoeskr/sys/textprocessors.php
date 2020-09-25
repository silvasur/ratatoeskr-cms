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

use r7r\cms\sys\Env;
use r7r\cms\sys\textprocessors\LegacyTextprocessor;
use r7r\cms\sys\textprocessors\TextprocessorRepository;

require_once(dirname(__FILE__) . "/utils.php");

/**
 * Register a textprocessor.
 *
 * @deprecated Use {@see TextprocessorRepository::register()} of the global {@see TextprocessorRepository} as returned by {@see Env::textprocessors()}.
 *
 * @param string $name The name of the textprocessor
 * @param callable $fx The textprocessor function (function($input), returns HTML)
 * @param bool $visible_in_backend Should this textprocessor be visible in the backend? Defaults to True.
 */
function textprocessor_register($name, $fx, $visible_in_backend=true)
{
    Env::getGlobal()->textprocessors()->register($name, new LegacyTextprocessor($fx, $visible_in_backend));
}

/**
 * Apply a textprocessor on a text.
 *
 * @param string $text The input text.
 * @param string $name The name of the textprocessor.
 *
 * @return string HTML
 * @throws Exception If the textprocessor is unknown
 * @deprecated Use {@see TextprocessorRepository::mustApply()} of the global {@see TextprocessorRepository} as returned by {@see Env::textprocessors()}.
 */
function textprocessor_apply($text, $name)
{
    return Env::getGlobal()->textprocessors()->mustApply((string)$text, (string)$name);
}

/**
 * Applies a textprocessor automatically on a {@see Translation} object.
 *
 * The used textprocessor is determined by the {@see Translation::$texttype} property.
 *
 * @param Translation $translationobj
 * @return string HTML
 * @deprecated Use {@see Translation::applyTextprocessor()} instead
 */
function textprocessor_apply_translation(Translation $translationobj)
{
    return $translationobj->applyTextprocessor(Env::getGlobal()->textprocessors());
}
