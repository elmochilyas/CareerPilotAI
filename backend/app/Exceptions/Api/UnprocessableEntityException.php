<?php

namespace App\Exceptions\Api;

use App\Support\ProblemDetails\ProblemDetailsException;

class UnprocessableEntityException extends ProblemDetailsException
{
    public function __construct(
        string $detail = 'Unprocessable entity.',
        string $errorCode = 'unprocessable_entity',
        array $errors = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct(422, $detail, $errorCode, $errors, $previous);
    }
}
