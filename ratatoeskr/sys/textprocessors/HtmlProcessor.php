<?php


namespace r7r\cms\sys\textprocessors;

/**
 * A simple textprocessor that assumes the input already is HTML.
 */
class HtmlProcessor implements Textprocessor
{
    public function apply(string $input): string
    {
        return $input;
    }

    public function showInBackend(): bool
    {
        return true;
    }
}
