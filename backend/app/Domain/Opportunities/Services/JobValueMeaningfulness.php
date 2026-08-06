<?php

namespace App\Domain\Opportunities\Services;

final readonly class JobValueMeaningfulness
{
    private const array PLACEHOLDER_PATTERNS = [
        '/^n\/a$/i',
        '/^unknown$/i',
        '/^not\s+specified$/i',
        '/^tbd$/i',
        '/^see\s+description$/i',
        '/^see\s+above$/i',
        '/^none$/i',
        '/^any$/i',
        '/^various$/i',
        '/^null$/i',
    ];

    public static function isMeaningfulString(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return false;
        }

        foreach (self::PLACEHOLDER_PATTERNS as $pattern) {
            if (preg_match($pattern, $trimmed)) {
                return false;
            }
        }

        return true;
    }

    public static function isMeaningfulSkill(array $item): bool
    {
        return isset($item['label'])
            && self::isMeaningfulString($item['label']);
    }

    public static function isMeaningfulLanguage(array $item): bool
    {
        return isset($item['language'])
            && self::isMeaningfulString($item['language']);
    }

    public static function isMeaningfulCertification(array $item): bool
    {
        return isset($item['name'])
            && self::isMeaningfulString($item['name']);
    }

    public static function isMeaningfulExperience(array $item): bool
    {
        return isset($item['summary'])
            && self::isMeaningfulString($item['summary']);
    }

    public static function isMeaningfulEducation(array $item): bool
    {
        return isset($item['degree'])
            && self::isMeaningfulString($item['degree']);
    }

    public static function isMeaningfulCompensation(?array $comp): bool
    {
        if ($comp === null || $comp === []) {
            return false;
        }

        foreach (['salary_min', 'salary_max', 'currency', 'period', 'text'] as $key) {
            if (isset($comp[$key]) && self::isMeaningfulString((string) $comp[$key])) {
                return true;
            }
        }

        return false;
    }

    public static function isMeaningfulBenefit(mixed $value): bool
    {
        return self::isMeaningfulString($value);
    }

    public static function isMeaningfulBenefitItem(array $item): bool
    {
        return isset($item['name'])
            && self::isMeaningfulString($item['name']);
    }

    public static function isMeaningfulDate(mixed $value): bool
    {
        return self::isMeaningfulString($value);
    }

    public static function isMeaningfulAdditionalRequirement(mixed $value): bool
    {
        return self::isMeaningfulString($value);
    }

    public static function isMeaningfulResponsibility(array $item): bool
    {
        return isset($item['text'])
            && self::isMeaningfulString($item['text']);
    }
}
