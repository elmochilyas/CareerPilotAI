<?php

namespace App\Domain\Matching\Exceptions;

use RuntimeException;
use Throwable;

final class RequirementClassifierException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $problemCode = 'ai_classifier_unexpected_failure',
        public readonly array $errors = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
