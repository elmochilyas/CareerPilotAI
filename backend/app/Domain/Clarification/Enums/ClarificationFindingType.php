<?php

namespace App\Domain\Clarification\Enums;

enum ClarificationFindingType: string
{
    case SkillMissing = 'skill_missing';

    case SkillClaimedNoEvidence = 'skill_claimed_no_evidence';

    case ExperienceMissing = 'experience_missing';

    case ExperienceAmbiguous = 'experience_ambiguous';

    case LanguageMissing = 'language_missing';

    case LanguageAmbiguous = 'language_ambiguous';

    case EvidenceMissing = 'evidence_missing';

    case EvidenceAmbiguous = 'evidence_ambiguous';
}
