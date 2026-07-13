<?php

namespace App\Support\ProblemDetails;

use Symfony\Component\HttpKernel\Exception\HttpException;

class ProblemDetailsException extends HttpException
{
    private string $errorCode;

    private array $errorBag;

    public function __construct(
        int $statusCode,
        string $detail = '',
        string $errorCode = 'unknown_error',
        array $errors = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($statusCode, $detail, $previous, [], $statusCode);

        $this->errorCode = $errorCode;
        $this->errorBag = $errors;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getErrorBag(): array
    {
        return $this->errorBag;
    }
}
