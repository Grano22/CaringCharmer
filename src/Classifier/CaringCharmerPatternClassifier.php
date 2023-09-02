<?php

namespace Grano22\CaringCharmer\Classifier;

use ArrayAccess;
use Grano22\CaringCharmer\Data\ClassifiedData;
use SplFixedArray;

interface CaringCharmerPatternClassifier
{
    public const UNCLASSIFIED = 'unclassified';

    /** @return array<string, ClassifiedData> */
    public function classify(array|SplFixedArray|ArrayAccess $dataToClassify): array;
}