<?php

namespace r7r\cms\sys\textprocessors;

/**
 * Interface Textprocessor.
 *
 * A textprocessor turns an input into HTML.
 */
interface Textprocessor
{
    public function apply(string $input): string;

    /**
     * Should this textprocessor be available to the user in the backend?
     * @return bool
     */
    public function showInBackend(): bool;
}
