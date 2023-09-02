<?php

declare(strict_types=1);

namespace Grano22\CaringCharmer;

use ArrayIterator;
use Grano22\CaringCharmer\Anonymizer\CaringCharmerPatternAnonymizer;
use Grano22\CaringCharmer\Classifier\CaringCharmerPatternClassifier;
use Grano22\CaringCharmer\Classifier\CaringCharmerPiiClassifier;
use Grano22\CaringCharmer\Data\ClassifiedData;
use Grano22\CaringCharmer\Recogniser\CaringCharmerRecogniser;
use Grano22\CaringCharmer\Tokenizer\CaringCharmerArrayTokenizer;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;

class CaringCharmer
{
    private CaringCharmerNormalizer $normalizer;

    public function __construct()
    {
        $this->normalizer = new CaringCharmerNormalizer();
    }

    public function tokenize(string $inputData, string $format): array
    {
        $normalizedData = $this->normalizer->from($format, $inputData);
        $arrayTokenizer = new CaringCharmerArrayTokenizer();

        return array_keys($arrayTokenizer->tokenize($normalizedData));
    }

    public function autoAnonymise(string $inputData, string $format): array
    {
        $normalizedData = $this->normalizer->from($format, $inputData);
        $arrayTokenizer = new CaringCharmerArrayTokenizer();
        $dataToClassify = $arrayTokenizer->tokenize($normalizedData);

//        $iterator = new RecursiveIteratorIterator(
//            new RecursiveArrayIterator($normalizedData),
//            RecursiveIteratorIterator::LEAVES_ONLY
//        );
//
//        foreach ($iterator as $key => $value) {
//            $dataToClassify[] = $value;
//        }

        $personalDataClassifier = new CaringCharmerPiiClassifier();
        $classifiedData = $personalDataClassifier->classify($dataToClassify);

        $patternAnonymizer = new CaringCharmerPatternAnonymizer();

        foreach ($classifiedData as $category => $classifiedDatum) {
            if ($category !== CaringCharmerPatternClassifier::UNCLASSIFIED) {
                /** @var ClassifiedData $classifiedEntry */
                foreach ($classifiedDatum as $classifiedEntry) {
                    $dataToClassify[$classifiedEntry->getId()] = $patternAnonymizer->anonymize($category, $classifiedEntry->getData());
                }
            }
        }

        return $arrayTokenizer->untokenize($dataToClassify);
    }
}