<?php

namespace App\Domain\Matching\Services;

final class FingerprintService
{
    public function profile(ProfileSnapshot $snapshot): string
    {
        return $this->hash($snapshot->toCanonicalArray());
    }

    public function opportunity(OpportunitySnapshot $snapshot): string
    {
        return $this->hash($snapshot->toCanonicalArray());
    }

    /**
     * @param  array<string, mixed>  $canonical
     */
    private function hash(array $canonical): string
    {
        return hash('sha256', json_encode(
            $canonical,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }
}
