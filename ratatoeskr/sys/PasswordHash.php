<?php


namespace r7r\cms\sys;

/**
 * Functions for creating and checking password hashes. Mostly wrappers around php's builtin password_\* functions but
 * can also verify our old legacy password hash.
 */
class PasswordHash
{
    private const PASSWORD_ALGO = \PASSWORD_DEFAULT;

    /** @var bool */
    private $isLegacy;

    /** @var string */
    private $hashData;

    private function __construct(bool $isLegacy, string $hashData)
    {
        $this->isLegacy = $isLegacy;
        $this->hashData = $hashData;
    }

    private static function verifyLegacy(string $password, string $pwhash): bool
    {
        list($iterations, $hexsalt) = explode('$', $pwhash);
        return self::hashLegacy($password, pack("H*", $hexsalt), $iterations) == $pwhash;
    }

    private static function hashLegacy(string $data, $salt, string $iterations): string
    {
        $hash = $data . $salt;
        for ($i = $iterations ;$i--;) {
            $hash = sha1($data . $hash . $salt, (bool) $i);
        }
        return $iterations . '$' . bin2hex($salt) . '$' . $hash;
    }

    private function format(): string
    {
        return $this->isLegacy
            ? $this->hashData
            : '!' . $this->hashData;
    }

    private static function parse(string $s): self
    {
        return substr($s, 0, 1) === '!'
            ? new self(false, substr($s, 1))
            : new self(true, $s);
    }

    /**
     * Verifies that a given password is valid for the given hash
     * @param string $password
     * @param string $hash
     * @return bool
     */
    public static function verify(string $password, string $hash): bool
    {
        $hash = self::parse($hash);
        return $hash->isLegacy
            ? self::verifyLegacy($password, $hash->hashData)
            : password_verify($password, $hash->hashData);
    }

    /**
     * Creates a hash for a password
     * @param string $password
     * @return string Treat this as opaque data. Don't rely on it being in a certain format, it might change in the future.
     */
    public static function hash(string $password): string
    {
        return (new self(false, password_hash($password, self::PASSWORD_ALGO)))->format();
    }

    /**
     * Checks, if a given hash should be recomputed (because it's not considered secure any more) if the password is known.
     * @param string $hash
     * @return bool
     */
    public static function needsRehash(string $hash): bool
    {
        $hash = self::parse($hash);
        return $hash->isLegacy
            ? true
            : password_needs_rehash($hash->hashData, self::PASSWORD_ALGO);
    }
}
