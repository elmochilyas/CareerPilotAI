<?php

namespace App\Domain\Matching\Services;

use App\Domain\Matching\Data\MatchAnalysisResult;
use App\Domain\Matching\Data\MatchFindingResult;
use App\Domain\Matching\Data\MatchScoreComponent;
use App\Domain\Matching\Enums\MatchCategory;
use App\Domain\Matching\Enums\MatchImportance;
use App\Domain\Matching\Enums\MatchState;

final class MatchScoreCalculator
{
    /**
     * @param  list<MatchFindingResult>  $findings
     * @param  array<string, bool>  $categoryHasCandidateData
     */
    public function calculate(array $findings, array $categoryHasCandidateData): MatchAnalysisResult
    {
        $findingsByCategory = [];
        foreach ($findings as $finding) {
            $findingsByCategory[$finding->category ?? ''][] = $finding;
        }

        $components = [];
        $weightedScoreSum = 0.0;
        $presentWeightSum = 0.0;

        foreach (MatchCategory::cases() as $category) {
            $categoryFindings = $findingsByCategory[$category->value] ?? [];

            if ($categoryFindings === []) {
                continue;
            }

            $decided = array_values(array_filter(
                $categoryFindings,
                static fn (MatchFindingResult $finding): bool => $finding->matchState !== MatchState::Unknown,
            ));

            if ($decided === []) {
                $score = (int) config('matching.neutral_score');
                $achievedPoints = 0.0;
                $totalPoints = (float) count($categoryFindings);
            } else {
                $achievedPoints = array_sum(array_map(
                    static fn (MatchFindingResult $finding): float => $finding->factor,
                    $decided,
                ));
                $totalPoints = (float) count($decided);
                $score = (int) round(100 * $achievedPoints / $totalPoints);
            }

            $weight = (float) config("matching.weights.{$category->value}");

            $components[] = new MatchScoreComponent(
                category: $category,
                weight: $weight,
                score: $score,
                achievedPoints: $achievedPoints,
                totalPoints: $totalPoints,
                hasCandidateData: $categoryHasCandidateData[$category->value] ?? false,
            );

            $weightedScoreSum += $score * $weight;
            $presentWeightSum += $weight;
        }

        $overallScore = $presentWeightSum > 0.0
            ? (int) round($weightedScoreSum / $presentWeightSum)
            : (int) config('matching.neutral_score');

        return new MatchAnalysisResult(
            overallScore: $overallScore,
            evidenceCoverageScore: $this->evidenceCoverageScore($findings),
            requiredCount: $this->countByImportance($findings, MatchImportance::Required),
            preferredCount: $this->countByImportance($findings, MatchImportance::Preferred),
            matchedCount: $this->countByState($findings, MatchState::Matched),
            partialCount: $this->countByState($findings, MatchState::Partial),
            gapCount: $this->countByState($findings, MatchState::Gap),
            unknownCount: $this->countByState($findings, MatchState::Unknown),
            scoreComponents: $components,
            findings: $findings,
            warnings: $this->criticalMissingWarnings($findings),
        );
    }

    /**
     * @param  list<MatchFindingResult>  $findings
     */
    private function evidenceCoverageScore(array $findings): int
    {
        if ($findings === []) {
            return 0;
        }

        $withEvidence = count(array_filter(
            $findings,
            static fn (MatchFindingResult $finding): bool => $finding->evidenceRefs !== [],
        ));

        return (int) round(100 * $withEvidence / count($findings));
    }

    /**
     * @param  list<MatchFindingResult>  $findings
     * @return list<string>
     */
    private function criticalMissingWarnings(array $findings): array
    {
        $warnings = [];

        foreach ($findings as $finding) {
            if ($finding->importance === MatchImportance::Required && $finding->matchState === MatchState::Gap) {
                $warnings[] = $finding->requirementText;
            }
        }

        return $warnings;
    }

    /**
     * @param  list<MatchFindingResult>  $findings
     */
    private function countByImportance(array $findings, MatchImportance $importance): int
    {
        return count(array_filter(
            $findings,
            static fn (MatchFindingResult $finding): bool => $finding->importance === $importance,
        ));
    }

    /**
     * @param  list<MatchFindingResult>  $findings
     */
    private function countByState(array $findings, MatchState $state): int
    {
        return count(array_filter(
            $findings,
            static fn (MatchFindingResult $finding): bool => $finding->matchState === $state,
        ));
    }
}
