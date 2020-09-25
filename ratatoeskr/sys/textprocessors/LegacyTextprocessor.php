<?php


namespace r7r\cms\sys\textprocessors;

/**
 * LegacyTextprocessor is used to wrap an old-style textprocessor into an Textprocessor object.
 */
class LegacyTextprocessor implements Textprocessor
{
    /** @var callable */
    private $fx;

    /** @var bool */
    private $visible_in_backend;

    public function __construct(callable $fx, $visible_in_backend)
    {
        $this->fx = $fx;
        $this->visible_in_backend = (bool)$visible_in_backend;
    }

    public function apply(string $input): string
    {
        return (string)call_user_func($this->fx, $input);
    }

    public function showInBackend(): bool
    {
        return $this->visible_in_backend;
    }
}
