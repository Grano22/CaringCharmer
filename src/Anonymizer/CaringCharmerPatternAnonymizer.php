<?php

declare(strict_types=1);

namespace Grano22\CaringCharmer\Anonymizer;

use Grano22\CaringCharmer\Data\DataCategory;
use RuntimeException;

class CaringCharmerPatternAnonymizer
{
    private array $anonymizers;

    public function __construct() {
        $this->anonymizers = [
            DataCategory::CREDIT_CARD =>
                static fn(mixed $data) => '0000-' . str_repeat((string)random_int(0, 9) . '-', 2) . '0000'
        ];
    }

    public function anonymize(string $patternCategory, mixed $data): mixed
    {
        if (!array_key_exists($patternCategory, $this->anonymizers)) {
            throw new RuntimeException("Lack of anonymizer for category $patternCategory");
        }

        return $this->anonymizers[$patternCategory]($data);
    }
}