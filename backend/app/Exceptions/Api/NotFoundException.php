<?php

namespace App\Exceptions\Api;

use App\Support\ProblemDetails\ProblemDetailsException;

class NotFoundException extends ProblemDetailsException
{
    public function __construct(
        string $detail = 'Resource not found.',
        string $errorCode = 'not_found',
        array $errors = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct(404, $detail, $errorCode, $errors, $previous);
    }
}
