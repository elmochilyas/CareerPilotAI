<?php

namespace App\Domain\Clarification\Services;

use App\Domain\Clarification\Data\QuestionTemplate;
use App\Domain\Clarification\Enums\ClarificationFindingType;
use App\Domain\Clarification\Enums\ClarificationQuestionType;
use App\Domain\Matching\Enums\MatchCategory;
use App\Domain\Matching\Enums\MatchImportance;
use App\Domain\Matching\Enums\MatchState;
use App\Models\MatchFinding;

final class QuestionTemplateRegistry
{
    public const REGISTRY_VERSION = '1.0.0';

    /**
     * Versioned deterministic templates keyed by finding type. Each template
     * defines the question shape; {requirement} and {importance} placeholders
     * are resolved from the finding when a template is composed.
     *
     * @var array<string, array{version: string, question_type: ClarificationQuestionType, prompt: string, detail: string, options: list<string>|null, unit: string|null}>
     */
    private const TEMPLATES = [
        'skill_missing' => [
            'version' => '1.0.0',
            'question_type' => ClarificationQuestionType::YesNoWithDetails,
            'prompt' => 'Do you have experience with {requirement}?',
            'detail' => 'The role lists {requirement} as {importance}, but it is not in your profile. If you have it, confirm and add evidence so it counts as trusted.',
            'options' => null,
            'unit' => null,
        ],
        'skill_claimed_no_evidence' => [
            'version' => '1.0.0',
            'question_type' => ClarificationQuestionType::YesNoWithDetails,
            'prompt' => 'Can you provide evidence for {requirement}?',
            'detail' => '{requirement} is {importance}, and it is currently claimed in your profile without evidence. Evidence lets it count as verified.',
            'options' => null,
            'unit' => null,
        ],
        'experience_missing' => [
            'version' => '1.0.0',
            'question_type' => ClarificationQuestionType::YesNoWithDetails,
            'prompt' => 'Do you have {requirement}?',
            'detail' => 'The role asks for {requirement} as {importance}, but no matching experience or education is in your profile. Confirm and add evidence if you have it.',
            'options' => null,
            'unit' => null,
        ],
        'experience_ambiguous' => [
            'version' => '1.0.0',
            'question_type' => ClarificationQuestionType::Number,
            'prompt' => 'How many years of experience do you have with {requirement}?',
            'detail' => 'Your profile only partially covers {requirement}, which is {importance}.',
            'options' => null,
            'unit' => 'years',
        ],
        'language_missing' => [
            'version' => '1.0.0',
            'question_type' => ClarificationQuestionType::YesNo,
            'prompt' => 'Do you speak {requirement}?',
            'detail' => 'The role lists {requirement} as {importance}, but it is not in your profile.',
            'options' => null,
            'unit' => null,
        ],
        'language_ambiguous' => [
            'version' => '1.0.0',
            'question_type' => ClarificationQuestionType::Select,
            'prompt' => 'How would you rate your proficiency in {requirement}?',
            'detail' => 'The role lists {requirement} as {importance}, and your current proficiency only partially satisfies it.',
            'options' => ['Basic', 'Conversational', 'Professional', 'Fluent', 'Native'],
            'unit' => null,
        ],
        'evidence_missing' => [
            'version' => '1.0.0',
            'question_type' => ClarificationQuestionType::YesNoWithDetails,
            'prompt' => 'Do you have {requirement}?',
            'detail' => 'The role asks about {requirement} as {importance}, but no supporting project or certification is in your profile.',
            'options' => null,
            'unit' => null,
        ],
        'evidence_ambiguous' => [
            'version' => '1.0.0',
            'question_type' => ClarificationQuestionType::YesNoWithDetails,
            'prompt' => 'Can you confirm {requirement} with evidence?',
            'detail' => 'The role asks about {requirement} as {importance}, and your profile only partially supports it.',
            'options' => null,
            'unit' => null,
        ],
    ];

    /**
     * Resolves the deterministic template for an eligible finding, composing
     * the finding's requirement and importance into the prompt and detail.
     * Returns null when the finding has no template (e.g. an unstructured or
     * unsupported category).
     */
    public function resolve(MatchFinding $finding): ?QuestionTemplate
    {
        $type = $this->findingTypeFor($finding);

        if ($type === null) {
            return null;
        }

        $definition = self::TEMPLATES[$type->value];

        return new QuestionTemplate(
            templateKey: $this->templateKey($type, $finding->importance),
            version: $definition['version'],
            questionType: $definition['question_type'],
            prompt: $this->compose($definition['prompt'], $finding),
            detail: $this->compose($definition['detail'], $finding),
            options: $definition['options'],
            unit: $definition['unit'],
        );
    }

    /**
     * Derives the deterministic finding type from the persisted finding's
     * category and match state. Unsupported categories or states yield null.
     */
    public function findingTypeFor(MatchFinding $finding): ?ClarificationFindingType
    {
        $category = $finding->category;
        $state = $finding->match_state;

        if (in_array($category, [MatchCategory::RequiredSkills->value, MatchCategory::PreferredSkills->value], true)) {
            return match ($state) {
                MatchState::Gap => ClarificationFindingType::SkillMissing,
                MatchState::Partial => ClarificationFindingType::SkillClaimedNoEvidence,
                default => null,
            };
        }

        if ($category === MatchCategory::ExperienceEducation->value) {
            return match ($state) {
                MatchState::Gap => ClarificationFindingType::ExperienceMissing,
                MatchState::Partial => ClarificationFindingType::ExperienceAmbiguous,
                default => null,
            };
        }

        if ($category === MatchCategory::LanguageSoft->value) {
            return match ($state) {
                MatchState::Gap => ClarificationFindingType::LanguageMissing,
                MatchState::Partial => ClarificationFindingType::LanguageAmbiguous,
                default => null,
            };
        }

        if ($category === MatchCategory::Evidence->value) {
            return match ($state) {
                MatchState::Gap => ClarificationFindingType::EvidenceMissing,
                MatchState::Partial => ClarificationFindingType::EvidenceAmbiguous,
                default => null,
            };
        }

        return null;
    }

    public function templateKey(ClarificationFindingType $type, MatchImportance $importance): string
    {
        return $type->value.'_'.$importance->value;
    }

    public function has(string $templateKey): bool
    {
        return in_array($templateKey, self::templateKeys(), true);
    }

    /**
     * @return list<string>
     */
    public function allTemplateKeys(): array
    {
        return self::templateKeys();
    }

    private function compose(string $pattern, MatchFinding $finding): string
    {
        $requirement = $finding->requirement_label !== null && $finding->requirement_label !== ''
            ? $finding->requirement_label
            : $finding->requirement_text;

        return str_replace(
            ['{requirement}', '{importance}'],
            [$requirement, $this->importancePhrase($finding->importance)],
            $pattern,
        );
    }

    private function importancePhrase(MatchImportance $importance): string
    {
        return match ($importance) {
            MatchImportance::Required => 'required for this role',
            MatchImportance::Preferred => 'preferred for this role',
        };
    }

    /**
     * @return list<string>
     */
    private static function templateKeys(): array
    {
        $keys = [];

        foreach (ClarificationFindingType::cases() as $type) {
            foreach (MatchImportance::cases() as $importance) {
                $keys[] = $type->value.'_'.$importance->value;
            }
        }

        return $keys;
    }
}
