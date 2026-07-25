<?php

namespace App\Exceptions\Api;

use App\Support\ProblemDetails\ProblemDetailsException;

class ConflictException extends ProblemDetailsException
{
    public function __construct(
        string $detail = 'Resource conflict.',
        string $errorCode = 'conflict',
        array $errors = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct(409, $detail, $errorCode, $errors, $previous);
    }
}
