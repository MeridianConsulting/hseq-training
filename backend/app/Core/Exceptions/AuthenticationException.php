<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

class AuthenticationException extends \RuntimeException
{
    public function __construct(string $message = 'No autorizado')
    {
        parent::__construct($message, 401);
    }
}
