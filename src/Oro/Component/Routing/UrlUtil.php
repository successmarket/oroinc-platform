<?php

namespace Oro\Component\Routing;

/**
 * The utility class that provides static methods to help building of URL.
 */
class UrlUtil
{
    private const SLASH = '/';

    /**
     * Returns the path being requested including to the given base URL.
     * If the given path does not start with the given base URL, the base URL is added.
     * The returned path is always starts with a slash character,
     * except both the path and the base URL are empty strings, in this case the result is an empty string as well.
     *
     * @param string $path    The path to check
     * @param string $baseUrl The root path, including the script filename (e.g. index.php) if one exists,
     *                        from which a HTTP request is executed
     *
     * @return string The path with the base URL
     */
    public static function getAbsolutePath(string $path, string $baseUrl): string
    {
        if (!$baseUrl) {
            return self::normalizePath($path);
        }

        if (!$path) {
            return self::normalizePath($baseUrl);
        }

        if ($baseUrl === self::SLASH) {
            if (self::startsWithSlash($path)) {
                return $path;
            }

            return $baseUrl . $path;
        }

        if (self::startsWith($path, $baseUrl)) {
            return self::normalizePath($path);
        }

        if (!self::endsWithSlash($baseUrl)) {
            $baseUrl .= self::SLASH;
        }

        if (self::startsWithSlash($path)) {
            return self::normalizePath($baseUrl . substr($path, 1));
        }

        return self::normalizePath($baseUrl . $path);
    }

    /**
     * Returns the path being requested relative to the given base URL.
     * If the given path starts with the given base URL, the base URL is removed.
     * The returned path is always starts with a slash character,
     * except both the path and the base URL are empty strings, in this case the result is an empty string as well.
     *
     * @param string $path    The path to check
     * @param string $baseUrl The root path, including the script filename (e.g. index.php) if one exists,
     *                        from which a HTTP request is executed
     *
     * @return string The path without the base URL
     */
    public static function getPathInfo(string $path, string $baseUrl): string
    {
        if (!$baseUrl) {
            return self::normalizePath($path);
        }

        if (!$path) {
            return self::SLASH;
        }

        if ($baseUrl === self::SLASH) {
            if (self::startsWithSlash($path)) {
                return $path;
            }

            return self::SLASH . $path;
        }

        if ($path === $baseUrl) {
            return self::SLASH;
        }

        if (!self::endsWithSlash($baseUrl)) {
            $baseUrl .= self::SLASH;
        }

        if (self::startsWith($path, $baseUrl)) {
            return substr($path, strlen($baseUrl) - 1);
        }

        return self::normalizePath($path);
    }

    /**
     * Concatenates the given paths and adds a slash character between paths if one is not already present.
     * If any of a path is an empty string, the method concatenates the remaining paths.
     * If all paths are empty strings, the result is an empty string as well.
     *
     * @param string[] $paths
     *
     * @return string
     */
    public static function join(string ...$paths): string
    {
        $result = '';
        foreach ($paths as $path) {
            $result = self::joinTwoPaths($result, $path);
        }

        return $result;
    }

    /**
     * @param string $path1
     * @param string $path2
     *
     * @return string
     */
    private static function joinTwoPaths(string $path1, string $path2): string
    {
        if (!$path1) {
            return $path2;
        }

        if (!$path2 || $path2 === self::SLASH) {
            return $path1;
        }

        $path1EndsWithSlash = self::endsWithSlash($path1);
        if (self::startsWithSlash($path2)) {
            if ($path1EndsWithSlash) {
                return $path1 . substr($path2, 1);
            }

            return $path1 . $path2;
        }
        if ($path1EndsWithSlash) {
            return $path1 . $path2;
        }

        return $path1 . self::SLASH . $path2;
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    private static function startsWithSlash(string $value): bool
    {
        return strpos($value, self::SLASH) === 0;
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    private static function endsWithSlash(string $value): bool
    {
        return substr($value, -1) === self::SLASH;
    }

    /**
     * @param string $haystack
     * @param string $needle
     *
     * @return bool
     */
    private static function startsWith(string $haystack, string $needle): bool
    {
        return strpos($haystack, $needle) === 0;
    }

    /**
     * @param string $path
     *
     * @return string
     */
    private static function normalizePath(string $path): string
    {
        if (!$path || self::startsWithSlash($path)) {
            return $path;
        }

        return self::SLASH . $path;
    }
}
