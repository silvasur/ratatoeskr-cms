<?php


namespace r7r\cms\sys\textprocessors;

use Michelf\Markdown;

/**
 * A textprocessor that uses markdown to generate HTML.
 */
class MarkdownProcessor implements Textprocessor
{
    public function apply(string $input): string
    {
        return Markdown::defaultTransform($input);
    }

    public function showInBackend(): bool
    {
        return true;
    }
}
