<?php


namespace r7r\cms\sys;

use r7r\cms\sys\textprocessors\TextprocessorRepository;

/**
 * Env holds several global global objects. It's basically a DI container.
 */
class Env
{
    /** @var self|null  */
    private static $globalInstance = null;

    private $lazyLoaded = [];

    private function lazy(string $ident, callable $callback)
    {
        if (!isset($this->lazyLoaded[$ident])) {
            $this->lazyLoaded[$ident] = $callback();
        }
        return $this->lazyLoaded[$ident];
    }

    public static function getGlobal(): self
    {
        self::$globalInstance = self::$globalInstance ?? new self();

        return self::$globalInstance;
    }

    public function textprocessors(): TextprocessorRepository
    {
        return $this->lazy("textprocessors", [TextprocessorRepository::class, 'buildDefault']);
    }

    public function database(): Database
    {
        return $this->lazy("database", static function () {
            global $config;

            return Database::fromConfig($config);
        });
    }
}
