<?php

declare(strict_types=1);

namespace Tests\Support;

use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi;

/**
 * The operations of openapi/openapi.yaml as "METHOD /path/{}" keys, with every path parameter
 * written as {}, so router paths, contract paths, and requested URLs can be compared.
 */
final class OpenApiOperations
{
    private const string SPECIFICATION = __DIR__ . '/../../../openapi/openapi.yaml';
    private const string KEY_FORMAT = '%s %s';
    private const string PARAMETER = '{}';
    private const string PARAMETER_PATTERN = '[^/]+';
    private const string PATTERN_FORMAT = '#^%s$#';
    private const string OPENING_BRACE = '{';
    private const string CLOSING_BRACE = '}';
    private const string QUERY_SEPARATOR = '?';

    /** @return list<string> */
    public static function all(): array
    {
        $specification = Reader::readFromYamlFile(self::SPECIFICATION, OpenApi::class);
        $operations = [];

        foreach ($specification->paths as $path => $item) {
            foreach (array_keys($item->getOperations()) as $method) {
                $operations[] = self::key($method, $path);
            }
        }

        sort($operations);

        return $operations;
    }

    /**
     * Writes every {...} parameter of a router or contract path as {}, including router
     * parameters whose pattern holds braces of its own, such as {id:[0-9a-f]{8}-...}.
     */
    public static function key(string $method, string $path): string
    {
        $normalized = '';
        $depth = 0;

        foreach (str_split($path) as $character) {
            if ($character === self::OPENING_BRACE) {
                $normalized .= $depth === 0 ? self::PARAMETER : '';
                $depth++;
            } elseif ($character === self::CLOSING_BRACE) {
                $depth--;
            } elseif ($depth === 0) {
                $normalized .= $character;
            }
        }

        return sprintf(self::KEY_FORMAT, strtoupper($method), $normalized);
    }

    /**
     * Whether a requested URL, with real values and maybe a query string, is the operation.
     */
    public static function matches(string $operation, string $method, string $url): bool
    {
        [$path] = explode(self::QUERY_SEPARATOR, $url, 2);
        $pattern = sprintf(self::PATTERN_FORMAT, str_replace(preg_quote(self::PARAMETER, '#'), self::PARAMETER_PATTERN, preg_quote($operation, '#')));

        return preg_match($pattern, sprintf(self::KEY_FORMAT, strtoupper($method), $path)) === 1;
    }
}
