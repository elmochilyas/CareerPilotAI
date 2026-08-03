<?php

namespace App\Domain\Opportunities\Actions;

use App\Exceptions\Api\UnprocessableEntityException;
use App\Models\JobOpportunityIngestion;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

class CreateIngestionAction
{
    public function execute(User $user, string $description, ?string $sourceUrl = null, ?string $personalLabel = null): JobOpportunityIngestion
    {
        $normalized = $this->normalizeDescription($description);

        $this->validateDescription($normalized);
        $this->validateSourceUrl($sourceUrl);
        $this->validateLabel($personalLabel);

        $contentHash = $this->computeContentHash($normalized);

        return JobOpportunityIngestion::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'content_hash' => $contentHash,
            ],
            [
                'source_description' => $description,
                'source_url' => $sourceUrl,
                'personal_label' => $personalLabel,
                'status' => 'draft',
                'version' => 1,
            ],
        );
    }

    public function normalizeDescription(string $description): string
    {
        $text = preg_replace('/\s+/u', ' ', $description);
        $text = trim($text);

        return $text;
    }

    public function computeContentHash(string $normalized): string
    {
        return hash('sha256', mb_strtolower($normalized));
    }

    public function detectDuplicate(User $user, string $contentHash): ?JobOpportunityIngestion
    {
        return JobOpportunityIngestion::where('user_id', $user->id)
            ->where('content_hash', $contentHash)
            ->first();
    }

    private function validateDescription(string $normalized): void
    {
        $minLength = Config::integer('job-ingestion.min_description_length', 50);
        $maxLength = Config::integer('job-ingestion.max_description_length', 100000);

        if (Str::length($normalized) < $minLength) {
            throw new UnprocessableEntityException(
                'Description must be at least '.$minLength.' characters.',
                'description_too_short',
            );
        }

        if (Str::length($normalized) > $maxLength) {
            throw new UnprocessableEntityException(
                'Description exceeds maximum length of '.$maxLength.' characters.',
                'description_too_large',
            );
        }
    }

    private function validateSourceUrl(?string $url): void
    {
        if ($url === null || $url === '') {
            return;
        }

        $maxLength = Config::integer('job-ingestion.max_source_url_length', 500);

        if (Str::length($url) > $maxLength) {
            throw new UnprocessableEntityException(
                'Source URL exceeds maximum length.',
                'source_url_too_long',
            );
        }

        if (! preg_match('/^https?:\/\//i', $url)) {
            throw new UnprocessableEntityException(
                'Source URL must be a valid HTTP or HTTPS URL.',
                'invalid_source_url',
            );
        }
    }

    private function validateLabel(?string $label): void
    {
        if ($label === null || $label === '') {
            return;
        }

        $maxLength = Config::integer('job-ingestion.max_label_length', 255);

        if (Str::length($label) > $maxLength) {
            throw new UnprocessableEntityException(
                'Personal label exceeds maximum length.',
                'label_too_long',
            );
        }
    }
}
