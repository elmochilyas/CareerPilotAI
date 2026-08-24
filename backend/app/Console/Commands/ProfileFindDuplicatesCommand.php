<?php

namespace App\Console\Commands;

use App\Domain\Profile\Services\ProfileDuplicateDetector;
use App\Models\CandidateProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ProfileFindDuplicatesCommand extends Command
{
    protected $signature = 'profile:find-duplicates
                            {--user= : Filter by user ID}
                            {--profile= : Filter by profile ID}
                            {--json= : Path to write JSON report (e.g., storage/app/profile-duplicates.json)}';

    protected $description = 'Dry-run duplicate detection for CandidateProfile (read-only, no writes)';

    public function handle(ProfileDuplicateDetector $detector): int
    {
        $query = CandidateProfile::query()->with(['items', 'candidateSkills.skill']);

        if ($this->option('user')) {
            $query->where('user_id', $this->option('user'));
        }

        if ($this->option('profile')) {
            $query->where('id', $this->option('profile'));
        }

        $profiles = $query->get();

        if ($profiles->isEmpty()) {
            $this->warn('No profiles found for given filter.');

            return self::SUCCESS;
        }

        $allReports = [];
        $totalExact = 0;
        $totalPossible = 0;
        $totalLegit = 0;

        foreach ($profiles as $profile) {
            $report = $detector->detectForProfile($profile);
            $allReports[] = $report;

            $totalExact += $report['summary']['exact'];
            $totalPossible += $report['summary']['possible'];
            $totalLegit += $report['summary']['legitimate'];

            $this->line("Profile #{$report['profile_id']} (User #{$report['user_id']}): {$report['summary']['exact']} exact, {$report['summary']['possible']} possible, {$report['summary']['legitimate']} legitimate_separate");

            foreach ($report['groups'] as $group) {
                $type = $group['entity_type'].'/'.$group['subtype'];
                $cls = $group['classification'];
                $ids = implode(',', $group['record_ids']);
                $norm = $group['normalized_identity'];
                $reason = $group['reason'];
                $sim = $group['similarity'] !== null ? ' sim='.round($group['similarity'], 2) : '';

                if ($cls === 'exact_duplicate') {
                    $this->info("  [EXACT] $type ids=[$ids] norm=[$norm] reason=[$reason]$sim → {$group['recommended_action']}");
                } elseif ($cls === 'possible_duplicate') {
                    $this->comment("  [POSSIBLE] $type ids=[$ids] norm=[$norm] reason=[$reason]$sim → {$group['recommended_action']}");
                } else {
                    $this->line("  [LEGIT] $type ids=[$ids] norm=[$norm] reason=[$reason]");
                }

                foreach ($group['records'] as $rec) {
                    $this->line('    - '.json_encode($rec, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                }
            }
        }

        $this->newLine();
        $this->info("Summary: {$totalExact} exact, {$totalPossible} possible, {$totalLegit} legitimate_separate across ".count($allReports).' profiles');

        $jsonPath = $this->option('json');
        if ($jsonPath) {
            $fullPath = base_path($jsonPath);
            // Ensure directory exists
            $dir = dirname($fullPath);
            if (! File::isDirectory($dir)) {
                File::makeDirectory($dir, 0755, true);
            }

            $payload = [
                'generated_at' => now()->toIso8601String(),
                'total_profiles' => count($allReports),
                'summary' => ['exact' => $totalExact, 'possible' => $totalPossible, 'legitimate' => $totalLegit],
                'profiles' => $allReports,
            ];

            File::put($fullPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->info("JSON report written to: $jsonPath (full: $fullPath)");
        }

        return self::SUCCESS;
    }
}
