<?php

namespace App\Exceptions;

use RuntimeException;

class DomainException extends RuntimeException
{
    public function __construct(string $message, private readonly int $httpStatus = 422)
    {
        parent::__construct($message);
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}
