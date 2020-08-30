<?php

namespace App\Exceptions;

use Exception;

class InvalidTokenCategoryException extends Exception
{
    protected $message = 'Invalid token category';
}
