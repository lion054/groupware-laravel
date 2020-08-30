<?php

namespace App\Exceptions;

use Exception;

class InvalidTokenDataException extends Exception
{
    protected $message = 'Invalid token data';
}
