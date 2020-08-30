<?php

namespace App\Exceptions;

use Exception;

class TokenNotFoundException extends Exception
{
    protected $message = 'Token not found';
}
