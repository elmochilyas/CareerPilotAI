<?php

use App\Domain\Clarification\Actions\ApplyProposalAction;
use App\Domain\Skills\Actions\AddEvidenceAction;
use App\Domain\Skills\Actions\CreateCandidateSkillAction;
use App\Domain\Skills\Actions\UpdateCandidateSkillAction;
use App\Domain\Skills\Enums\SkillState;

arch('candidate skills are only mutated through the trusted skills actions')
    ->group('clarifications', 'arch')
    ->expect([
        'App\Domain\AIOperations',
        'App\Domain\Applications',
        'App\Domain\Clarification\Actions\BuildQuestionSessionAction',
        'App\Domain\Clarification\Actions\BuildProposalAction',
        'App\Domain\Clarification\Actions\CreateAnswerAction',
        'App\Domain\Clarification\Actions\ListClarificationsAction',
        'App\Domain\Clarification\Actions\ReviewAnswerAction',
        'App\Domain\Clarification\Actions\SkipQuestionAction',
        'App\Domain\Clarification\Services',
        'App\Domain\CvIngestion',
        'App\Domain\Identity',
        'App\Domain\Learning',
        'App\Domain\Matching',
        'App\Domain\Opportunities',
        'App\Domain\Platform',
        'App\Domain\Profile',
        'App\Domain\Resumes',
    ])
    ->not->toUse([
        AddEvidenceAction::class,
        CreateCandidateSkillAction::class,
        UpdateCandidateSkillAction::class,
    ]);

arch('no code outside the skills domain and clarification reasons about skill state')
    ->group('clarifications', 'arch')
    ->expect([
        'App\Domain\AIOperations',
        'App\Domain\Applications',
        'App\Domain\CvIngestion',
        'App\Domain\Identity',
        'App\Domain\Learning',
        'App\Domain\Matching',
        'App\Domain\Opportunities',
        'App\Domain\Platform',
        'App\Domain\Profile',
        'App\Domain\Resumes',
    ])
    ->not->toUse(SkillState::class);

arch('the clarification controller delegates to actions and never persists directly')
    ->group('clarifications', 'arch')
    ->expect('App\Http\Controllers\Api\V1\ClarificationController')
    ->not->toUse([
        'Illuminate\Support\Facades\DB',
        ApplyProposalAction::class,
    ]);
