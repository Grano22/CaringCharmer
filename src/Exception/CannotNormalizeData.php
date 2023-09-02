<?php

declare(strict_types=1);

namespace Grano22\CaringCharmer\Exception;

use Exception;
use RuntimeException;

class CannotNormalizeData extends ExceptionCreatedFromApplication implements ReasonBasedException
{
    public const REASON_MISSING_SUPPORT_FOR_FORMAT = 'missing_support_for_format';
    public const REASON_INVALID_STRUCTURE = 'invalid_structure';

    public static function fromFormat(string $format, ?Exception $lastException = null): self
    {
        return new self(self::REASON_UNKNOWN, $format);
    }

    public static function dueToMissingSupportForFormat(string $format): self
    {
        return new self(self::REASON_MISSING_SUPPORT_FOR_FORMAT, $format);
    }

    public static function dueToInvalidDataStructure(string $format, ?Exception $lastException = null): self
    {
        return new self(self::REASON_INVALID_STRUCTURE, $format, $lastException);
    }

    private function __construct(
        private string $reason,
        private string $format,
        ?Exception $lastException = null
    ) {
        parent::__construct(
            match ($this->reason) {
                self::REASON_UNKNOWN => "Cannot normalize from $this->format, unknown reason",
                self::REASON_INVALID_STRUCTURE => "Invalid structure $this->format",
                self::REASON_MISSING_SUPPORT_FOR_FORMAT => "Missing support for format $this->format",
                default => throw new RuntimeException("Unhandled reason $this->reason message in " . __CLASS__)
            },
            0,
            $lastException
        );
    }

    public function getFormat(): string
    {
        return $this->format;
    }
    public function getReason(): string
    {
        return $this->reason;
    }
}
