<?php

declare(strict_types=1);

namespace Grano22\CaringCharmer;

use Closure;
use Grano22\CaringCharmer\Exception\CannotNormalizeData;
use JsonException;

class CaringCharmerNormalizer
{
    private array $normalizers;

    public function __construct()
    {
        $this->normalizers = [
            'json' => Closure::fromCallable([$this, 'fromJson'])
        ];
    }

    /**
     * @throws CannotNormalizeData
     */
    public function from(string $format, string $input): array
    {
        if (!array_key_exists($format, $this->normalizers)) {
            throw CannotNormalizeData::dueToMissingSupportForFormat($format);
        }

        return $this->normalizers[$format]($input);
    }

    /**
     * @throws CannotNormalizeData
     */
    public function fromJson(string $jsonString): array
    {
        try {
            return json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
        } catch(JsonException $jsonException) {
            throw CannotNormalizeData::dueToInvalidDataStructure('json', $jsonException);
        }
    }
}