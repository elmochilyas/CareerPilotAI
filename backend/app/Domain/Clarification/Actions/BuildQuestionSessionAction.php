<?php

namespace App\Domain\Clarification\Actions;

use App\Domain\Clarification\Data\AssistantQuestion;
use App\Domain\Clarification\Data\ClarificationAssistantRequest;
use App\Domain\Clarification\Data\QuestionTemplate;
use App\Domain\Clarification\Enums\ClarificationQuestionStatus;
use App\Domain\Clarification\Exceptions\ClarificationAssistantException;
use App\Domain\Clarification\Services\ClarificationAssistantSchemaValidator;
use App\Domain\Clarification\Services\ClarificationAuditWriter;
use App\Domain\Clarification\Services\Contracts\ClarificationAssistant;
use App\Domain\Clarification\Services\QuestionTemplateRegistry;
use App\Domain\Matching\Enums\MatchImportance;
use App\Domain\Matching\Enums\MatchState;
use App\Domain\Skills\Enums\SkillState;
use App\Models\CandidateProfile;
use App\Models\ClarificationQuestion;
use App\Models\MatchAnalysis;
use App\Models\MatchFinding;

/**
 * Builds the open clarification session for a completed analysis. Eligible
 * findings become pending questions ordered by impact and capped per pass.
 * A finding is never duplicated within an analysis, so rebuilding the session
 * never creates a second question for the same finding.
 *
 * When the assistant is enabled, it may rank and reword the already-eligible
 * deterministic questions under schema validation. On any assistant failure the
 * deterministic set is used unchanged and the fallback reason is recorded in
 * each question's AI metadata. The assistant never adds, removes, or changes
 * eligibility, and never sees the final match score.
 */
final class BuildQuestionSessionAction
{
    public function __construct(
        private readonly QuestionTemplateRegistry $templates,
        private readonly ClarificationAssistant $assistant,
        private readonly ClarificationAssistantSchemaValidator $validator,
    ) {}

    /**
     * @return list<ClarificationQuestion>
     */
    public function execute(MatchAnalysis $analysis): array
    {
        $planned = $this->plannedFor($analysis);

        if ($planned === []) {
            return [];
        }

        $outcome = $this->applyAssistant($planned);

        $nextQuestionNo = $this->nextQuestionNo($analysis);

        $created = [];

        foreach ($outcome['planned'] as $entry) {
            $template = $entry['template'];

            $created[] = $question = ClarificationQuestion::create([
                'match_analysis_id' => $analysis->id,
                'match_finding_id' => $entry['finding']->id,
                'question_no' => $nextQuestionNo,
                'question_type' => $template->questionType,
                'prompt' => $template->prompt,
                'detail' => $template->detail,
                'template_key' => $template->templateKey,
                'options_json' => $template->options,
                'unit' => $template->unit,
                'status' => ClarificationQuestionStatus::Pending,
                'ai_metadata' => $outcome['metadata'],
            ]);

            // Append-only audit for question creation (best-effort)
            try {
                $userId = CandidateProfile::whereKey($analysis->candidate_profile_id)->value('user_id');
                if ($userId !== null) {
                    app(ClarificationAuditWriter::class)->write('question_created', [
                        'user_id' => $userId,
                        'match_analysis_id' => $analysis->id,
                        'target_id' => $question->id,
                        'field' => $question->template_key,
                        'metadata' => ['template_key' => $question->template_key],
                    ]);
                }
            } catch (\Throwable $ignored) {
            }

            $nextQuestionNo++;
        }

        return $created;
    }

    /**
     * Returns how many questions a generate call would create for the analysis
     * right now: eligible, not-yet-questioned findings with a resolvable
     * template, ordered by impact and capped per pass. It is the frontend
     * entry-point signal; zero means generation would create nothing.
     */
    public function eligibleCount(MatchAnalysis $analysis): int
    {
        return count($this->plannedFor($analysis));
    }

    /**
     * Plans the deterministic question set for the analysis: eligible findings
     * become template entries ordered by impact and capped per pass. A finding
     * with no resolvable template (e.g. an unclassified unknown) is skipped.
     *
     * @return list<array{finding: MatchFinding, template: QuestionTemplate}>
     */
    private function plannedFor(MatchAnalysis $analysis): array
    {
        $existingFindingIds = array_map(
            'intval',
            ClarificationQuestion::query()
                ->where('match_analysis_id', $analysis->id)
                ->whereNotNull('match_finding_id')
                ->pluck('match_finding_id')
                ->all(),
        );

        $findings = $analysis->findings()
            ->with('matchedCandidateSkill')
            ->get()
            ->filter(fn (MatchFinding $finding): bool => $this->isEligible($finding, $existingFindingIds))
            ->sortBy(fn (MatchFinding $finding): array => $this->impactSortKey($finding))
            ->values()
            ->take((int) config('clarification.session.max_questions', 3));

        /** @var list<array{finding: MatchFinding, template: QuestionTemplate}> $planned */
        $planned = [];

        foreach ($findings as $finding) {
            $template = $this->templates->resolve($finding);

            if ($template === null) {
                continue;
            }

            $planned[] = [
                'finding' => $finding,
                'template' => $template,
            ];
        }

        return $planned;
    }

    /**
     * Applies the assistant's ranked order and reworded prompts to the
     * deterministic set when enabled. A successful schema-validated result
     * reorders the questions and records assistant provenance; any failure
     * keeps the deterministic set and records the fallback reason.
     *
     * @param  list<array{finding: MatchFinding, template: QuestionTemplate}>  $planned
     * @return array{planned: list<array{finding: MatchFinding, template: QuestionTemplate}>, metadata: array<string, mixed>|null}
     */
    private function applyAssistant(array $planned): array
    {
        if (! (bool) config('clarification.assistant.enabled', false)) {
            return ['planned' => $planned, 'metadata' => null];
        }

        $request = new ClarificationAssistantRequest(
            questions: array_map(
                fn (array $entry): AssistantQuestion => new AssistantQuestion(
                    ref: (string) $entry['finding']->id,
                    questionType: $entry['template']->questionType,
                    prompt: $entry['template']->prompt,
                    requirement: $this->requirementLabel($entry['finding']),
                    evidenceBasis: $entry['template']->detail,
                ),
                $planned,
            ),
        );

        try {
            $result = $this->assistant->rankAndReword($request);
            $this->validator->validate($result, $request);

            $byRef = [];
            foreach ($planned as $entry) {
                $byRef[(string) $entry['finding']->id] = $entry;
            }

            $reordered = [];
            foreach ($result->questions as $question) {
                $entry = $byRef[$question->ref] ?? null;

                if ($entry === null) {
                    continue;
                }

                $entry['template'] = $entry['template']->withPrompt($question->rewordedPrompt);
                $reordered[] = $entry;
            }

            if (count($reordered) !== count($planned)) {
                return $this->fallback($planned);
            }

            return [
                'planned' => $reordered,
                'metadata' => [
                    'assistant' => true,
                    'provider' => $result->provider,
                    'model' => $result->model,
                    'prompt_version' => $result->promptVersion,
                    'schema_version' => $result->schemaVersion,
                ],
            ];
        } catch (ClarificationAssistantException $exception) {
            return $this->fallback($planned, $exception->problemCode);
        }
    }

    /**
     * @param  list<array{finding: MatchFinding, template: QuestionTemplate}>  $planned
     * @return array{planned: list<array{finding: MatchFinding, template: QuestionTemplate}>, metadata: array<string, mixed>|null}
     */
    private function fallback(array $planned, string $reason = 'ai_assistant_unexpected_failure'): array
    {
        return [
            'planned' => $planned,
            'metadata' => [
                'assistant' => false,
                'fallback_reason' => $reason,
            ],
        ];
    }

    /**
     * @param  list<int>  $existingFindingIds
     */
    private function isEligible(MatchFinding $finding, array $existingFindingIds): bool
    {
        if (! in_array($finding->match_state, [MatchState::Partial, MatchState::Gap, MatchState::Unknown], true)) {
            return false;
        }

        $factor = (float) $finding->factor;

        if ($factor !== 0.0 && $factor !== 0.5) {
            return false;
        }

        // A preferred-skill gap or unknown is classified as low-impact and never questioned.
        if (
            $finding->importance === MatchImportance::Preferred
            && in_array($finding->match_state, [MatchState::Gap, MatchState::Unknown], true)
        ) {
            return false;
        }

        // A trusted, rejected, or archived skill is never questioned.
        $skill = $finding->matchedCandidateSkill;

        if ($skill !== null && in_array($skill->state, [SkillState::Verified, SkillState::Rejected, SkillState::Archived], true)) {
            return false;
        }

        // Never duplicate a finding within the analysis.
        return ! in_array($finding->id, $existingFindingIds, true);
    }

    /**
     * Orders by impact: required before preferred, then the larger factor gap
     * first, then stable ties by display order and id.
     *
     * @return array{0: int, 1: float, 2: int, 3: int}
     */
    private function impactSortKey(MatchFinding $finding): array
    {
        return [
            $finding->importance === MatchImportance::Required ? 0 : 1,
            (float) $finding->factor,
            $finding->display_order,
            $finding->id,
        ];
    }

    private function requirementLabel(MatchFinding $finding): string
    {
        return $finding->requirement_label !== null && $finding->requirement_label !== ''
            ? $finding->requirement_label
            : $finding->requirement_text;
    }

    private function nextQuestionNo(MatchAnalysis $analysis): int
    {
        $max = ClarificationQuestion::query()
            ->where('match_analysis_id', $analysis->id)
            ->max('question_no');

        return (int) $max + 1;
    }
}
