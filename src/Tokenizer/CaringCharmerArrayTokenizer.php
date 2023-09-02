<?php

declare(strict_types=1);

namespace Grano22\CaringCharmer\Tokenizer;

use RecursiveArrayIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class CaringCharmerArrayTokenizer
{
    public function untokenize(array $targetTokenizedMap): array
    {
        $untokenizedArray = [];

        foreach ($targetTokenizedMap as $tokenizedPath => $value) {
            $paths = $this->parseTokenizedPaths($tokenizedPath);

            $nextArrayRef = &$untokenizedArray;
            $totalPaths = count($paths) - 1;

            for ($i = 0; $i <= $totalPaths; $i++) {
                $pathPart = $paths[$i];

                if ($totalPaths !== $i) {
                    if (!array_key_exists($pathPart, $nextArrayRef)) {
                        $nextArrayRef[$pathPart] = [];
                    }

                    $nextArrayRef = &$nextArrayRef[$pathPart];

                    continue;
                }

                $nextArrayRef[$pathPart] = $value;
            }
        }

        return $untokenizedArray;
    }

    public function tokenize(array $originalArray): array
    {
        $tokenizedPaths = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveArrayIterator($originalArray),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $key => $value) {
            $tokenizedPaths[$this->tokenizePathFromRecursiveArrayIterator($iterator)] = $value;
        }

        return $tokenizedPaths;
    }

    private function parseTokenizedPaths(string $tokenizedPaths): array
    {
        $paths = [];
        $chars = str_split($tokenizedPaths);
        $inStringedPath = false;
        $inIndexedPath = false;
        $path = '';

        for ($i = 1; $i < count($chars); $i++) {
            $char = $chars[$i];

            if ($char === "'") {
                if (!$inStringedPath) {
                    if ($path !== '') {
                        throw new RuntimeException('Parse error: path is not empty');
                    }

                    $inStringedPath = true;

                    continue;
                }

                if ($chars[max(1, $i - 1)] === '\\') {
                    $path .= $char;

                    continue;
                }

                $paths[] = $path;
                $path = '';
                $inStringedPath = false;

                continue;
            }

            if ($inStringedPath) {
                $path .= $char;

                continue;
            }

            if ($char === '[') {
                if ($inIndexedPath) {
                    throw new RuntimeException(
                        "Indexed path can be opened only once at $i: " . substr($tokenizedPaths, 0, $i)
                    );
                }

                $inIndexedPath = true;

                if ($path !== '') {
                    $paths[] = $path;
                    $path = '';
                }

                continue;
            }

            if ($char === ']') {
                if (!$inIndexedPath) {
                    throw new RuntimeException(
                        "Indexed path can be closed only once at $i: " . substr($tokenizedPaths, 0, $i)
                    );
                }

                $paths[] = intval($path);
                $path = '';
                $inIndexedPath = false;

                continue;
            }

            if ($inIndexedPath) {
                if (!ctype_digit(strval($char))) {
                    throw new RuntimeException(
                        "Indexed path must contains only numbers at $i: " . substr($tokenizedPaths, 0, $i) . ", but got: $char"
                    );
                }

                $path .= $char;

                continue;
            }

            if ($char === '.') {
                if ($chars[max(1, $i - 1)] === ']') {
                    continue;
                }

                if (!$path) {
                    throw new RuntimeException(
                        "Path cannot be empty at $i: " . substr($tokenizedPaths, 0, $i)
                    );
                }

                $paths[] = $path;
                $path = '';

                continue;
            }

            $path .= $char;
        }

        if ($path) {
            $paths[] = $path;
        }

        return $paths;
    }

    private function tokenizePathFromRecursiveArrayIterator(RecursiveIteratorIterator &$recursiveIterator): string
    {
        $path = '.';

        $i = 0;
        while ($i <= $recursiveIterator->getDepth())
        {
            $nextIterator = $i === $recursiveIterator->getDepth() ?
                $recursiveIterator :
                $recursiveIterator->getSubIterator($i)
            ;

            $pathPart = $nextIterator->key();
            $path .= is_int($pathPart) ?
                "[$pathPart]" :
                ($i ? '.' : '') . str_pad(
                    $pathPart,
                    str_replace(' ', '', $pathPart) !== $pathPart ? strlen($pathPart) + 2 : 0,
                    "'",
                    STR_PAD_BOTH
                );

            $i++;
        }

        return $path;
    }
}