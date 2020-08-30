<?php

namespace App\Exceptions;

use Exception;

class TokenNotVerifiedException extends Exception
{
    protected $message = 'Token not verified';
}
