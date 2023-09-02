<?php

namespace Grano22\CaringCharmer\Exception;

use Exception;
use Grano22\CaringCharmer\ApplicationMetaInformation;

abstract class ExceptionCreatedFromApplication extends Exception implements ApplicationException, ApplicationMetaInformation
{
    public function getUniqId(): string
    {
        $baseExceptionInfo = [self::APP_INFO_VERSION, get_class($this)];

        if ($this instanceof ReasonBasedException) {
            $baseExceptionInfo[] = $this->getReason();
        }

        return base64_encode(implode('_', $baseExceptionInfo));
    }
}