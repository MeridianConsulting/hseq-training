<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

class ValidationException extends \RuntimeException
{
    private array $errors;

    public function __construct(array $errors, string $message = 'Error de validación')
    {
        $this->errors = $errors;
        parent::__construct($message, 422);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
