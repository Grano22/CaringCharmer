<?php

namespace Grano22\CaringCharmer\Classifier;

use ArrayAccess;
use Grano22\CaringCharmer\Data\ClassifiedData;
use Grano22\CaringCharmer\Recogniser\CaringCharmerCreditCardRecogniser;
use Grano22\CaringCharmer\Recogniser\CaringCharmerRecogniser;
use SplFixedArray;

class CaringCharmerPiiClassifier implements CaringCharmerPatternClassifier
{
    public const CREDIT_CARDS = 'credit_cards';

    /** @var CaringCharmerRecogniser[] $recognisers */
    private array $recognisers;

    public function __construct() {
        $this->recognisers = [
            new CaringCharmerCreditCardRecogniser()
        ];
    }

    /** @return array<string, ClassifiedData[]> */
    public function classify(array|SplFixedArray|ArrayAccess $dataToClassify): array
    {
        $classifiedPatterns = [
            self::UNCLASSIFIED => []
        ];

        foreach ($dataToClassify as $dataKey => $partialDataToClassify) {
            foreach ($this->recognisers as $recogniser) {
                $potentiallyRecognisedData = $recogniser->recognise($partialDataToClassify);

                if ($potentiallyRecognisedData) {
                    if (!array_key_exists($recogniser::CATEGORY_NAME, $classifiedPatterns)) {
                        $classifiedPatterns[$recogniser::CATEGORY_NAME] = [];
                    }

                    $classifiedPatterns[$recogniser::CATEGORY_NAME][] = new ClassifiedData(
                        (string)$dataKey,
                        $potentiallyRecognisedData->getData()
                    );

                    continue 2;
                }
            }

            $classifiedPatterns[self::UNCLASSIFIED][] = new ClassifiedData((string)$dataKey, $partialDataToClassify);
        }

        return $classifiedPatterns;
    }
}