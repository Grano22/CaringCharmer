<?php

declare(strict_types=1);

namespace Grano22\CaringCharmer\Exception;

interface ReasonBasedException
{
    public const REASON_UNKNOWN = 'unknown';

    public function getReason(): string;
}