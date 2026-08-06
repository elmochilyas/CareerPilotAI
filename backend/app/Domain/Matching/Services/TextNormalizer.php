<?php

namespace App\Domain\Matching\Services;

final class TextNormalizer
{
    /** Function words that carry no discriminative value for requirement matching. */
    private const STOPWORDS = [
        'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from',
        'in', 'is', 'it', 'of', 'on', 'or', 'that', 'the', 'this', 'to',
        'with', 'experience', 'year', 'years',
    ];

    public static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    /**
     * @return list<string>
     */
    public static function tokens(string $value): array
    {
        $parts = preg_split('/[^a-z0-9]+/', self::normalize($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(
            $parts,
            static fn (string $token): bool => ! in_array($token, self::STOPWORDS, true)
                && preg_match('/[a-z]/', $token) === 1,
        ));
    }

    /**
     * Fraction of the requirement vocabulary covered by the candidate text.
     */
    public static function similarity(string $requirement, string $candidateText): float
    {
        $requirementTokens = self::tokens($requirement);
        $candidateTokens = self::tokens($candidateText);

        if ($requirementTokens === [] || $candidateTokens === []) {
            return 0.0;
        }

        $covered = count(array_intersect($requirementTokens, $candidateTokens));

        return $covered / count($requirementTokens);
    }
}
