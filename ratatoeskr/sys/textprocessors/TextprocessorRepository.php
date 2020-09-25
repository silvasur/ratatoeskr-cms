<?php


namespace r7r\cms\sys\textprocessors;

use Exception;

class TextprocessorRepository
{
    /** @var Textprocessor[] */
    private $textprocessors = [];


    /**
     * Builds a repository with the default textprocessors prepopulated.
     * @return self
     */
    public static function buildDefault(): self
    {
        $repo = new self();

        $repo->register("Markdown", new MarkdownProcessor());
        $repo->register("Plain Text", new PlainTextProcessor());
        $repo->register("HTML", new HtmlProcessor());

        return $repo;
    }

    public function register(string $name, Textprocessor $textprocessor): void
    {
        $this->textprocessors[$name] = $textprocessor;
    }

    public function getTextprocessor(string $name): ?Textprocessor
    {
        return $this->textprocessors[$name] ?? null;
    }

    /**
     * @return Textprocessor[]
     */
    public function all(): array
    {
        return $this->textprocessors;
    }

    /**
     * Apply a textprocessor to the input text.
     *
     * @param string $input The input text
     * @param string $name The name of the textprocessor
     * @return string|null Will return null, if the textprocessor was not found
     */
    public function apply(string $input, string $name): ?string
    {
        $textprocessor = $this->getTextprocessor($name);
        return $textprocessor === null ? null : $textprocessor->apply($input);
    }

    /**
     * Like {@see TextprocessorRepository::apply()}, but will throw an exception instead of returning null, if the textprocessor was not found.
     *
     * @param string $input The input text
     * @param string $name The name of the textprocessor
     * @return string
     * @throws Exception
     */
    public function mustApply(string $input, string $name): string
    {
        $out = $this->apply($input, $name);
        if ($out === null) {
            throw new Exception("Unknown Textprocessor: $name");
        }
        return $out;
    }
}
