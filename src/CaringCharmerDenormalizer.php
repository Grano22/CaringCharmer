<?php

declare(strict_types=1);

namespace Grano22\CaringCharmer;

use Closure;
use RuntimeException;

class CaringCharmerDenormalizer
{
    private array $denormalizers;

    public function __construct()
    {
        $this->denormalizers = [
            'json' => Closure::fromCallable([$this, 'toJson'])
        ];
    }

    public function to(string $format, array $input): string
    {
        if (!array_key_exists($format, $this->denormalizers)) {
            throw new RuntimeException("Denormalizer for format $format is not available");
        }

        return $this->denormalizers[$format]($input);
    }

    public function toJson(array $inputData): string
    {
        $encodedData = json_encode($inputData, JSON_PRETTY_PRINT);

        if (!$encodedData) {
            throw new RuntimeException("Cannot denormalize to format json");
        }

        return $encodedData;
    }
}