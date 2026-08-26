<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanDegenerateEvidenceCommand extends Command
{
    protected $signature = 'opportunities:clean-degenerate-evidence
                            {--dry-run : Show what would be changed without writing}';

    protected $description = 'Null degenerate source_evidence (generic section headings) on requirements, skills and pending suggestions';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry-run mode — no writes will be performed.');
        }

        $stats = [
            'requirements' => $this->cleanRequirements($dryRun),
            'skills' => $this->cleanSkills($dryRun),
            'suggestions' => $this->cleanSuggestions($dryRun),
        ];

        $this->newLine();
        $this->info(sprintf(
            'Done. Requirements: %d cleaned, Skills: %d cleaned, Suggestions: %d cleaned%s.',
            $stats['requirements'],
            $stats['skills'],
            $stats['suggestions'],
            $dryRun ? ' (dry-run)' : '',
        ));

        return self::SUCCESS;
    }

    private function cleanRequirements(bool $dryRun): int
    {
        $cleaned = 0;

        $opportunityIds = DB::table('job_requirements')
            ->whereNotNull('source_evidence')
            ->distinct()
            ->pluck('job_opportunity_id');

        foreach ($opportunityIds as $oppId) {
            $categories = DB::table('job_requirements')
                ->where('job_opportunity_id', $oppId)
                ->whereNotNull('source_evidence')
                ->distinct()
                ->pluck('category');

            foreach ($categories as $category) {
                $rows = DB::table('job_requirements')
                    ->where('job_opportunity_id', $oppId)
                    ->where('category', $category)
                    ->whereNotNull('source_evidence')
                    ->get(['id', 'source_evidence']);

                if ($rows->isEmpty()) {
                    continue;
                }

                $evidences = $rows->pluck('source_evidence')->map(fn ($v) => $this->normalizeEvidence($v))->all();

                // Check degenerate group
                if ($this->isDegenerateEvidenceGroup($evidences)) {
                    $this->line("  [DEGENERATE GROUP] opp={$oppId} category={$category} evidence=".var_export($rows->first()->source_evidence, true).' count='.count($rows));
                    $ids = $rows->pluck('id')->all();
                    $cleaned += count($ids);
                    if (! $dryRun) {
                        DB::table('job_requirements')->whereIn('id', $ids)->update(['source_evidence' => null]);
                    }

                    continue;
                }

                // Otherwise check individual generic headings
                foreach ($rows as $row) {
                    $norm = $this->normalizeEvidence($row->source_evidence);
                    if ($norm !== null && $this->isGenericHeading($norm)) {
                        $this->line("  [GENERIC] opp={$oppId} category={$category} id={$row->id} evidence=".var_export($row->source_evidence, true));
                        $cleaned++;
                        if (! $dryRun) {
                            DB::table('job_requirements')->where('id', $row->id)->update(['source_evidence' => null]);
                        }
                    }
                }
            }
        }

        $this->info("Requirements: {$cleaned} rows".($dryRun ? ' would be' : '').' cleaned.');

        return $cleaned;
    }

    private function cleanSkills(bool $dryRun): int
    {
        $cleaned = 0;

        $oppIds = DB::table('job_opportunity_skills')
            ->whereNotNull('source_evidence')
            ->distinct()
            ->pluck('job_opportunity_id');

        foreach ($oppIds as $oppId) {
            $rows = DB::table('job_opportunity_skills')
                ->where('job_opportunity_id', $oppId)
                ->whereNotNull('source_evidence')
                ->get(['id', 'source_evidence']);

            if ($rows->isEmpty()) {
                continue;
            }

            $evidences = $rows->pluck('source_evidence')->map(fn ($v) => $this->normalizeEvidence($v))->all();

            if ($this->isDegenerateEvidenceGroup($evidences)) {
                $this->line("  [DEGENERATE GROUP] skills opp={$oppId} evidence=".var_export($rows->first()->source_evidence, true).' count='.count($rows));
                $ids = $rows->pluck('id')->all();
                $cleaned += count($ids);
                if (! $dryRun) {
                    DB::table('job_opportunity_skills')->whereIn('id', $ids)->update(['source_evidence' => null]);
                }

                continue;
            }

            foreach ($rows as $row) {
                $norm = $this->normalizeEvidence($row->source_evidence);
                if ($norm !== null && $this->isGenericHeading($norm)) {
                    $this->line("  [GENERIC] skills opp={$oppId} id={$row->id} evidence=".var_export($row->source_evidence, true));
                    $cleaned++;
                    if (! $dryRun) {
                        DB::table('job_opportunity_skills')->where('id', $row->id)->update(['source_evidence' => null]);
                    }
                }
            }
        }

        $this->info("Skills: {$cleaned} rows".($dryRun ? ' would be' : '').' cleaned.');

        return $cleaned;
    }

    private function cleanSuggestions(bool $dryRun): int
    {
        $cleaned = 0;

        $ingestionIds = DB::table('job_opportunity_suggestions')
            ->whereNotNull('source_evidence')
            ->distinct()
            ->pluck('ingestion_id');

        foreach ($ingestionIds as $ingestionId) {
            $types = DB::table('job_opportunity_suggestions')
                ->where('ingestion_id', $ingestionId)
                ->whereNotNull('source_evidence')
                ->distinct()
                ->pluck('type');

            foreach ($types as $type) {
                $rows = DB::table('job_opportunity_suggestions')
                    ->where('ingestion_id', $ingestionId)
                    ->where('type', $type)
                    ->whereNotNull('source_evidence')
                    ->get(['id', 'source_evidence']);

                if ($rows->isEmpty()) {
                    continue;
                }

                $evidences = $rows->pluck('source_evidence')->map(fn ($v) => $this->normalizeEvidence($v))->all();

                if ($this->isDegenerateEvidenceGroup($evidences)) {
                    $this->line("  [DEGENERATE GROUP] suggestion ingestion={$ingestionId} type={$type} evidence=".var_export($rows->first()->source_evidence, true).' count='.count($rows));
                    $ids = $rows->pluck('id')->all();
                    $cleaned += count($ids);
                    if (! $dryRun) {
                        DB::table('job_opportunity_suggestions')->whereIn('id', $ids)->update(['source_evidence' => null]);
                    }

                    continue;
                }

                foreach ($rows as $row) {
                    $norm = $this->normalizeEvidence($row->source_evidence);
                    if ($norm !== null && $this->isGenericHeading($norm)) {
                        $this->line("  [GENERIC] suggestion ingestion={$ingestionId} type={$type} id={$row->id} evidence=".var_export($row->source_evidence, true));
                        $cleaned++;
                        if (! $dryRun) {
                            DB::table('job_opportunity_suggestions')->where('id', $row->id)->update(['source_evidence' => null]);
                        }
                    }
                }
            }
        }

        $this->info("Suggestions: {$cleaned} rows".($dryRun ? ' would be' : '').' cleaned.');

        return $cleaned;
    }

    private function normalizeEvidence(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        $trimmed = rtrim($trimmed, " :\t-–—•");
        $trimmed = trim($trimmed);

        if ($trimmed === '' || mb_strlen($trimmed) > 500) {
            return null;
        }

        return $trimmed;
    }

    private function isGenericHeading(string $value): bool
    {
        if (mb_strlen($value) >= 40) {
            return false;
        }

        if (preg_match('/[.!?]/u', $value)) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<int, string|null>  $evidences  normalized
     */
    private function isDegenerateEvidenceGroup(array $evidences): bool
    {
        $nonNull = array_values(array_filter($evidences, fn (?string $v) => $v !== null && trim($v) !== ''));

        if (count($nonNull) < 2) {
            return false;
        }

        $first = mb_strtolower(trim($nonNull[0]));

        foreach ($nonNull as $ev) {
            if (mb_strtolower(trim((string) $ev)) !== $first) {
                return false;
            }
        }

        $sample = trim($nonNull[0]);

        if (mb_strlen($sample) >= 40) {
            return false;
        }

        if (preg_match('/[.!?]/u', $sample)) {
            return false;
        }

        return true;
    }
}
