<?php

namespace App\Domain\Clarification\Enums;

final class ClarificationProposalField
{
    public const STATE = 'state';

    public const EVIDENCE = 'evidence';

    public const YEARS_EXPERIENCE = 'years_experience';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::STATE,
            self::EVIDENCE,
            self::YEARS_EXPERIENCE,
        ];
    }
}
