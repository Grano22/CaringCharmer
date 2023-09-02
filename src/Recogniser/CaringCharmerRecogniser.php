<?php

declare(strict_types=1);

namespace Grano22\CaringCharmer\Recogniser;

use Grano22\CaringCharmer\Data\RecognisedDataDescriptor;

interface CaringCharmerRecogniser
{
    public const CATEGORY_NAME = 'unnamed';

    public function recognise(mixed $value): ?RecognisedDataDescriptor;

    public function canBeRecognised(mixed $value): bool;
}