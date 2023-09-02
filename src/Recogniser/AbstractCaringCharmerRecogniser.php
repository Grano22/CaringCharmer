<?php

declare(strict_types=1);

namespace Grano22\CaringCharmer\Recogniser;

use Grano22\CaringCharmer\Data\RecognisedDataDescriptor;

abstract class AbstractCaringCharmerRecogniser implements CaringCharmerRecogniser
{
    public function recognise(mixed $value): ?RecognisedDataDescriptor
    {
        if (!$this->canBeRecognised($value)) {
            return null;
        }

        return new RecognisedDataDescriptor(
            $value,
            $this->isFaked($value),
            $this->isSensitive($value)
        );
    }

    abstract protected function isFaked(mixed $value): bool;
    abstract protected function isSensitive(mixed $value): bool;

    protected function canBeRecognisedByOneOfRegexpPatterns(array $regexpPatterns, string $value): bool
    {
        foreach ($regexpPatterns as $regexpPattern) {
            if (!!preg_match($regexpPattern, $value)) {
                return true;
            }
        }

        return false;
    }
}