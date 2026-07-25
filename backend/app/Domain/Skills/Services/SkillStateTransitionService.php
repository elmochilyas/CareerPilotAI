<?php

namespace App\Domain\Skills\Services;

use App\Domain\Skills\Enums\SkillState;

class SkillStateTransitionService
{
    private const TRANSITIONS = [
        'claimed->verified' => ['requiresEvidence' => true],
        'claimed->learning' => ['requiresEvidence' => false],
        'claimed->archived' => ['requiresEvidence' => false],
        'claimed->rejected' => ['requiresEvidence' => false],
        'verified->claimed' => ['requiresEvidence' => false],
        'verified->learning' => ['requiresEvidence' => false],
        'verified->archived' => ['requiresEvidence' => false],
        'verified->rejected' => ['requiresEvidence' => false],
        'learning->verified' => ['requiresEvidence' => true],
        'learning->claimed' => ['requiresEvidence' => false],
        'learning->archived' => ['requiresEvidence' => false],
        'learning->rejected' => ['requiresEvidence' => false],
        'rejected->claimed' => ['requiresEvidence' => false],
        'rejected->learning' => ['requiresEvidence' => false],
        'rejected->archived' => ['requiresEvidence' => false],
        'archived->claimed' => ['requiresEvidence' => false],
        'archived->learning' => ['requiresEvidence' => false],
        'archived->verified' => ['requiresEvidence' => true],
        'archived->rejected' => ['requiresEvidence' => false],
    ];

    public function isTransitionAllowed(SkillState $from, SkillState $to): bool
    {
        $key = "{$from->value}->{$to->value}";

        return isset(self::TRANSITIONS[$key]);
    }

    public function requiresEvidence(SkillState $from, SkillState $to): bool
    {
        $key = "{$from->value}->{$to->value}";

        return self::TRANSITIONS[$key]['requiresEvidence'] ?? false;
    }

    public function getAllowedTransitions(?SkillState $state = null): array
    {
        $map = [];
        foreach (SkillState::cases() as $from) {
            $map[$from->value] = [];
            foreach (SkillState::cases() as $to) {
                if ($this->isTransitionAllowed($from, $to)) {
                    $map[$from->value][] = [
                        'to' => $to->value,
                        'requires_evidence' => $this->requiresEvidence($from, $to),
                    ];
                }
            }
        }

        return $state !== null ? $map[$state->value] : $map;
    }

    public function getAllowedFromStates(?SkillState $to = null): array
    {
        $map = [];
        foreach (SkillState::cases() as $from) {
            foreach (SkillState::cases() as $t) {
                if ($this->isTransitionAllowed($from, $t)) {
                    $map[$t->value][] = $from->value;
                }
            }
        }

        return $to !== null ? ($map[$to->value] ?? []) : $map;
    }
}
