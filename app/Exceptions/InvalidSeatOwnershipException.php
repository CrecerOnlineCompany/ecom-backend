<?php

namespace App\Exceptions;

class InvalidSeatOwnershipException extends \Exception
{
    public function __construct($message = "Invalid seat ownership", $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
