<?php

declare(strict_types=1);

namespace Grano22\CaringCharmer\Data;

class RecognisedDataDescriptor
{
    public function __construct(
        private mixed $data,
        private bool $faked,
        private bool $sensitive,
        private bool $dangerous = false
    ) {}

    public function getData(): mixed
    {
        return $this->data;
    }

    public function isFake(): bool
    {
        return $this->faked;
    }

    public function isSensitive(): bool
    {
        return $this->sensitive;
    }

    public function isDangerous(): bool
    {
        return $this->dangerous;
    }
}