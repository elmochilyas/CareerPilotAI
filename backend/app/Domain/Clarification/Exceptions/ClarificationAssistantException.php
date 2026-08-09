<?php

namespace App\Domain\Clarification\Exceptions;

use RuntimeException;
use Throwable;

final class ClarificationAssistantException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $problemCode = 'ai_assistant_unexpected_failure',
        public readonly array $errors = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
