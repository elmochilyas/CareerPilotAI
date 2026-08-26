<?php

namespace App\Domain\CompanyResearch\Services;

use App\Models\Company;

class CompanyResolver
{
    /**
     * @var string[]
     */
    private const SUFFIXES = [
        'inc',
        'incorporated',
        'llc',
        'ltd',
        'limited',
        'corp',
        'corporation',
        'co',
        'company',
        'group',
        'sas',
        'sarl',
        's.a.r.l',
        's.a.r.l.',
        'gmbh',
        'sa',
        'spa',
        'bv',
        'pty',
    ];

    /**
     * @var string[]
     */
    private const STOPWORDS = [
        'company',
        'group',
        'corp',
        'corporation',
        'inc',
    ];

    public function normalizeName(string $name): string
    {
        $normalized = trim($name);

        if ($normalized === '') {
            return '';
        }

        // Unicode NFC
        if (class_exists(\Normalizer::class)) {
            $nfc = \Normalizer::normalize($normalized, \Normalizer::FORM_C);

            if ($nfc !== false) {
                $normalized = $nfc;
            }
        }

        $normalized = mb_strtolower($normalized, 'UTF-8');

        // Replace any whitespace sequence with single space
        $normalized = (string) preg_replace('/\s+/u', ' ', $normalized);

        // Trim leading/trailing punctuation and spaces
        $normalized = trim($normalized, " \t\n\r\0\x0B.,;:!\"'()[]{}");

        // Iteratively strip suffixes
        $changed = true;

        while ($changed) {
            $changed = false;

            foreach (self::SUFFIXES as $suffix) {
                $suffixLower = mb_strtolower($suffix, 'UTF-8');
                // Match suffix preceded by comma/space/dot optionally, at end with optional dot
                $pattern = '/[\s,.\-]*'.preg_quote($suffixLower, '/').'\.?$/u';

                if (preg_match($pattern, $normalized) === 1) {
                    $candidate = (string) preg_replace($pattern, '', $normalized);
                    $candidate = trim($candidate, " \t\n\r\0\x0B.,");

                    if ($candidate !== '' && $candidate !== $normalized) {
                        $normalized = $candidate;
                        $changed = true;

                        break;
                    }
                }
            }
        }

        // Collapse whitespace again and trim punctuation
        $normalized = (string) preg_replace('/\s+/u', ' ', $normalized);
        $normalized = trim($normalized, " \t\n\r\0\x0B.,;:!\"'()[]{}");

        return $normalized;
    }

    public function canonicalizeDomain(?string $website): ?string
    {
        if ($website === null || trim($website) === '') {
            return null;
        }

        $input = trim($website);

        // Ensure parseable by adding scheme if missing
        if (! str_contains($input, '://')) {
            $input = 'https://'.$input;
        }

        $parts = parse_url($input);

        if ($parts === false || ! isset($parts['host'])) {
            return null;
        }

        $host = mb_strtolower((string) $parts['host'], 'UTF-8');
        $host = trim($host);

        // Strip www.
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        // IDN to ascii if possible
        if (function_exists('idn_to_ascii')) {
            $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

            if (is_string($ascii) && $ascii !== '') {
                $host = $ascii;
            }
        }

        // Validate domain
        if (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            // fallback to filter as hostname via idn check - allow if parse gave host
            if ($host === '' || str_contains($host, '/') || str_contains($host, ' ')) {
                return null;
            }
        }

        return $host;
    }

    public function isAmbiguous(string $normalizedName, ?string $canonicalDomain): bool
    {
        if ($normalizedName === '') {
            return true;
        }

        if (mb_strlen($normalizedName, 'UTF-8') < 3) {
            return true;
        }

        if (in_array($normalizedName, self::STOPWORDS, true)) {
            return true;
        }

        // If name is very generic single word and no domain, ambiguous
        if (! str_contains($normalizedName, ' ') && mb_strlen($normalizedName, 'UTF-8') <= 4 && $canonicalDomain === null) {
            // e.g., "Acme" alone without domain could still be okay, but keep as not ambiguous for now
            // Only treat truly stopword-like
        }

        return false;
    }

    /**
     * Resolve or create company for opportunity context.
     * Returns null when identity is uncertain.
     */
    public function resolveOrCreate(?string $companyName, ?string $website): ?Company
    {
        $normalized = $companyName !== null ? $this->normalizeName($companyName) : '';
        $canonical = $this->canonicalizeDomain($website);

        if ($normalized === '' && $canonical === null) {
            return null;
        }

        if ($this->isAmbiguous($normalized, $canonical)) {
            // If we have a solid domain, we can still proceed even if name ambiguous
            if ($canonical === null) {
                return null;
            }
            // With domain, ambiguous name is okay — proceed via domain lookup
        }

        // 1) Try canonical domain match first
        if ($canonical !== null) {
            $byDomain = Company::query()
                ->where('website_canonical', $canonical)
                ->first();

            if ($byDomain !== null) {
                return $byDomain;
            }

            // Also check website field fallback (for rows not yet backfilled)
            $byWebsite = Company::query()
                ->where(function ($q) use ($canonical) {
                    $q->where('website', 'like', '%'.$canonical.'%');
                })
                ->first();

            if ($byWebsite !== null) {
                // Lazy backfill canonical
                if ($byWebsite->website_canonical === null) {
                    $byWebsite->update(['website_canonical' => $canonical]);
                }

                return $byWebsite;
            }
        }

        // 2) Try normalized name exact match
        if ($normalized !== '') {
            $byNormalized = Company::query()
                ->where('name_normalized', $normalized)
                ->first();

            if ($byNormalized !== null) {
                // If canonical differs and both have domains, do not auto-merge ambiguous
                if ($canonical !== null && $byNormalized->website_canonical !== null && $byNormalized->website_canonical !== $canonical) {
                    // Different domains + same normalized name could be ambiguous coincidence (e.g., "Acme Inc" domain acme.com vs acme.co)
                    // Treat as ambiguous — do not merge
                    return null;
                }

                return $byNormalized;
            }

            // Fallback: case-insensitive name match after normalization for legacy rows without name_normalized
            $fallback = Company::query()
                ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($companyName ?? ''), 'UTF-8')])
                ->first();

            if ($fallback !== null) {
                return $fallback;
            }
        }

        // Create new company if we have reliable identity
        $displayName = $companyName !== null ? trim($companyName) : '';

        if ($displayName === '') {
            // If we only have domain, use domain as name fallback? Better not invent — require name
            if ($canonical !== null) {
                $displayName = $canonical;
            } else {
                return null;
            }
        }

        // Final ambiguity guard before create
        if ($this->isAmbiguous($normalized, $canonical) && $canonical === null) {
            return null;
        }

        return Company::query()->create([
            'name' => $displayName,
            'website' => $website,
            'name_normalized' => $normalized !== '' ? $normalized : null,
            'website_canonical' => $canonical,
            'research_status' => 'not_researched',
            'research_version' => 1,
        ]);
    }

    /**
     * Resolve without creation—for research that can operate without company row.
     */
    public function resolveExisting(?string $companyName, ?string $website): ?Company
    {
        $normalized = $companyName !== null ? $this->normalizeName($companyName) : '';
        $canonical = $this->canonicalizeDomain($website);

        if ($normalized === '' && $canonical === null) {
            return null;
        }

        if ($canonical !== null) {
            $byDomain = Company::query()->where('website_canonical', $canonical)->first();

            if ($byDomain !== null) {
                return $byDomain;
            }
        }

        if ($normalized !== '') {
            $byNormalized = Company::query()->where('name_normalized', $normalized)->first();

            if ($byNormalized !== null) {
                return $byNormalized;
            }
        }

        return null;
    }
}
