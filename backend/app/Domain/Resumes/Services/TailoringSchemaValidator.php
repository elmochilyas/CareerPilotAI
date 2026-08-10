<?php

namespace App\Domain\Resumes\Services;

use App\Domain\Resumes\Enums\TailoringChangeType;
use InvalidArgumentException;

final class TailoringSchemaValidator
{
    /**
     * Validate the AI rewriter output against the expected schema.
     *
     * @param  array<string, mixed>  $aiOutput
     * @param  list<int>  $validSourceIds
     *
     * @throws InvalidArgumentException
     */
    public function validate(array $aiOutput, array $validSourceIds = []): bool
    {
        if (! isset($aiOutput['proposals']) || ! is_array($aiOutput['proposals'])) {
            throw new InvalidArgumentException(
                'Invalid AI output: missing or non-array "proposals" key.',
            );
        }

        foreach ($aiOutput['proposals'] as $index => $proposal) {
            $this->validateProposal($proposal, $index, $validSourceIds);
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $proposal
     * @param  list<int>  $validSourceIds
     *
     * @throws InvalidArgumentException
     */
    private function validateProposal(array $proposal, int $index, array $validSourceIds): void
    {
        $prefix = "proposals[{$index}]";

        if (! isset($proposal['source_type']) || ! is_string($proposal['source_type'])) {
            throw new InvalidArgumentException(
                "{$prefix}: missing or invalid 'source_type'.",
            );
        }

        if (! isset($proposal['source_id']) || ! is_int($proposal['source_id'])) {
            throw new InvalidArgumentException(
                "{$prefix}: missing or invalid 'source_id'.",
            );
        }

        if (! isset($proposal['original_text']) || ! is_string($proposal['original_text'])) {
            throw new InvalidArgumentException(
                "{$prefix}: missing or invalid 'original_text'.",
            );
        }

        if (! isset($proposal['proposed_text']) || ! is_string($proposal['proposed_text'])) {
            throw new InvalidArgumentException(
                "{$prefix}: missing or invalid 'proposed_text'.",
            );
        }

        if (! isset($proposal['change_type']) || ! is_string($proposal['change_type'])) {
            throw new InvalidArgumentException(
                "{$prefix}: missing or invalid 'change_type'.",
            );
        }

        $changeType = TailoringChangeType::tryFrom($proposal['change_type']);

        if ($changeType === null) {
            throw new InvalidArgumentException(
                "{$prefix}: invalid change_type '{$proposal['change_type']}'.",
            );
        }

        if ($proposal['original_text'] === '' && $changeType !== TailoringChangeType::Include) {
            throw new InvalidArgumentException(
                "{$prefix}: original_text cannot be empty for change_type '{$changeType->value}'.",
            );
        }

        if ($proposal['proposed_text'] === '' && $changeType !== TailoringChangeType::Exclude) {
            throw new InvalidArgumentException(
                "{$prefix}: proposed_text cannot be empty for change_type '{$changeType->value}'.",
            );
        }

        if ($validSourceIds !== [] && ! in_array($proposal['source_id'], $validSourceIds, true)) {
            throw new InvalidArgumentException(
                "{$prefix}: source_id {$proposal['source_id']} is not a valid profile item ID.",
            );
        }
    }
}
