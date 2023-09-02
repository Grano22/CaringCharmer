<?php

namespace Grano22\CaringCharmer\Exception;

interface ApplicationException
{
    public function getUniqId(): string;
}