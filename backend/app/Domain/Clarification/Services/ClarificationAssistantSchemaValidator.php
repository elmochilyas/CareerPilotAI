<?php

namespace App\Domain\Clarification\Services;

use App\Domain\Clarification\Data\ClarificationAssistantRequest;
use App\Domain\Clarification\Data\ClarificationAssistantResult;
use App\Domain\Clarification\Exceptions\ClarificationAssistantException;
use Illuminate\Support\Facades\Config;

class ClarificationAssistantSchemaValidator
{
    private const MAX_REWORDED_PROMPT_LENGTH = 500;

    public function validate(ClarificationAssistantResult $result, ClarificationAssistantRequest $request): void
    {
        $errors = [];

        $expectedSchemaVersion = (string) Config::get('clarification.assistant.schema_version', '1.0.0');

        if ($result->schemaVersion !== $expectedSchemaVersion) {
            $errors[] = 'schema_version';
        }

        if (count($result->questions) !== count($request->questions)) {
            $errors[] = 'questions count must match the number of requested questions';
        }

        $allowedRefs = [];
        foreach ($request->questions as $question) {
            $allowedRefs[$question->ref] = true;
        }

        $seen = [];

        foreach ($result->questions as $question) {
            if (! isset($allowedRefs[$question->ref])) {
                $errors[] = "ref {$question->ref}: not a requested question";
            }

            if (isset($seen[$question->ref])) {
                $errors[] = "ref {$question->ref}: duplicate question";
            }
            $seen[$question->ref] = true;

            $reworded = trim($question->rewordedPrompt);

            if ($reworded === '') {
                $errors[] = "ref {$question->ref}: reworded prompt must not be empty";
            }

            if (mb_strlen($reworded) > self::MAX_REWORDED_PROMPT_LENGTH) {
                $errors[] = "ref {$question->ref}: reworded prompt exceeds maximum length";
            }
        }

        if ($errors !== []) {
            throw new ClarificationAssistantException(
                'Clarification assistant output did not pass schema and business validation.',
                'ai_assistant_schema_invalid',
                $errors,
            );
        }
    }
}
