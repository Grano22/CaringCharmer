<?php

declare(strict_types=1);

namespace Grano22\CaringCharmer\Data;

class ClassifiedData
{
    public function __construct(
        private string $id,
        private mixed $data,
        private array $labels = []
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    public function getLabels(): array
    {
        return $this->labels;
    }
}