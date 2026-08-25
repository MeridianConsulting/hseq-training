<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

class HttpException extends \RuntimeException
{
    private int $statusCode;

    public function __construct(string $message = 'Error del servidor', int $statusCode = 500, ?\Throwable $previous = null)
    {
        $this->statusCode = $statusCode;
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
