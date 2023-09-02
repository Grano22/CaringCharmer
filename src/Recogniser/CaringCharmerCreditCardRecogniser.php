<?php

namespace Grano22\CaringCharmer\Recogniser;

use Grano22\CaringCharmer\Data\DataCategory;

class CaringCharmerCreditCardRecogniser extends AbstractCaringCharmerRecogniser
{
    public const CATEGORY_NAME = DataCategory::CREDIT_CARD;
    private const REGEXP_PATTERNS = [
        /** @lang PhpRegExp */ '/(?:\d{4}-?){4}/'
    ];

    public function canBeRecognised(mixed $value): bool
    {
        return $this->canBeRecognisedByOneOfRegexpPatterns(self::REGEXP_PATTERNS, (string)$value);
    }

    protected function isFaked(mixed $value): bool
    {
        return false;
    }

    protected function isSensitive(mixed $value): bool
    {
        return true;
    }
}