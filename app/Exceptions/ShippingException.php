<?php

namespace App\Exceptions;

use RuntimeException;

class ShippingException extends RuntimeException
{
    public function __construct(string $message, private readonly int $httpStatus = 503)
    {
        parent::__construct($message);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}
