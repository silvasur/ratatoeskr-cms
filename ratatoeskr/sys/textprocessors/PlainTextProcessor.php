<?php


namespace r7r\cms\sys\textprocessors;

use r7r\cms\sys\Esc;

/**
 * A textprocessor that simply escapes the input string.
 */
class PlainTextProcessor implements Textprocessor
{
    public function apply(string $input): string
    {
        return Esc::esc($input, Esc::HTML_WITH_BR);
    }

    public function showInBackend(): bool
    {
        return true;
    }
}
