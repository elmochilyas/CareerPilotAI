<?php

namespace App\Domain\Matching\Services;

use App\Domain\Matching\Data\ClassifierRequest;
use App\Domain\Matching\Data\ClassifierRequestItem;
use App\Domain\Matching\Data\ClassifierResult;
use App\Domain\Matching\Enums\ClassifierComparisonKind;
use App\Domain\Matching\Enums\MatchCategory;
use App\Domain\Matching\Enums\MatchState;
use App\Domain\Matching\Exceptions\RequirementClassifierException;
use Illuminate\Support\Facades\Config;

class RequirementClassifierSchemaValidator
{
    private const VALID_EVIDENCE_TYPES = [
        'profile_item',
        'skill',
        'certification',
        'project',
        'experience',
        'education',
    ];

    private const MAX_JUSTIFICATION_LENGTH = 2000;

    private const MAX_LABEL_LENGTH = 255;

    public function validate(ClassifierResult $result, ClassifierRequest $request): void
    {
        $errors = [];

        $expectedSchemaVersion = (string) Config::get('matching.classifier_schema_version', '1.0.0');

        if ($result->schemaVersion !== $expectedSchemaVersion) {
            $errors[] = 'schema_version';
        }

        if (count($result->findings) !== count($request->items)) {
            $errors[] = 'findings count must match the number of requested requirements';
        }

        $indexes = array_map(
            static fn (ClassifierRequestItem $item): int => $item->index,
            $request->items,
        );

        $itemsByIndex = [];
        foreach ($request->items as $item) {
            $itemsByIndex[$item->index] = $item;
        }

        $allowedItemIds = array_map(
            static fn (array $profileItem): int => $profileItem['id'],
            $request->profileItems,
        );

        $seen = [];

        foreach ($result->findings as $finding) {
            if (! in_array($finding->index, $indexes, true)) {
                $errors[] = "index {$finding->index}: not a requested requirement";
            }

            if (in_array($finding->index, $seen, true)) {
                $errors[] = "index {$finding->index}: duplicate finding";
            }
            $seen[] = $finding->index;

            $item = $itemsByIndex[$finding->index] ?? null;

            if ($finding->confidence !== null && ($finding->confidence < 0.0 || $finding->confidence > 1.0)) {
                $errors[] = "index {$finding->index}: confidence out of range";
            }

            if ($finding->justification !== null && mb_strlen($finding->justification) > self::MAX_JUSTIFICATION_LENGTH) {
                $errors[] = "index {$finding->index}: justification exceeds maximum length";
            }

            if ($item === null) {
                continue;
            }

            if ($item->kind !== ClassifierComparisonKind::Unstructured && $finding->category !== null) {
                $errors[] = "index {$finding->index}: category only allowed for unstructured requirements";
            }

            if ($item->kind === ClassifierComparisonKind::Unstructured) {
                if ($finding->category !== null && MatchCategory::tryFrom($finding->category) === null) {
                    $errors[] = "index {$finding->index}: invalid classified category";
                }

                if ($finding->category === null && $finding->matchState !== MatchState::Unknown) {
                    $errors[] = "index {$finding->index}: unclassified requirement must be marked unknown";
                }
            }

            $evidenceWithId = [];

            foreach ($finding->evidenceReferences as $reference) {
                if (! in_array($reference['type'], self::VALID_EVIDENCE_TYPES, true)) {
                    $errors[] = "index {$finding->index}: invalid evidence type";
                }

                if ($reference['label'] !== null && mb_strlen($reference['label']) > self::MAX_LABEL_LENGTH) {
                    $errors[] = "index {$finding->index}: evidence label exceeds maximum length";
                }

                if ($reference['id'] === null) {
                    continue;
                }

                $evidenceWithId[] = $reference['id'];

                if (! in_array($reference['id'], $allowedItemIds, true)) {
                    $errors[] = "index {$finding->index}: evidence references an unknown profile item";
                }
            }

            if (
                in_array($finding->matchState, [MatchState::Matched, MatchState::Partial], true)
                && $evidenceWithId === []
            ) {
                $errors[] = "index {$finding->index}: matched or partial finding must reference at least one profile item";
            }
        }

        if ($errors !== []) {
            throw new RequirementClassifierException(
                'Classifier output did not pass schema and business validation.',
                'ai_classifier_schema_invalid',
                $errors,
            );
        }
    }
}
