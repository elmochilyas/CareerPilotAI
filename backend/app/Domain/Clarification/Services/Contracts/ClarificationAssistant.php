<?php

namespace App\Domain\Clarification\Services\Contracts;

use App\Domain\Clarification\Data\ClarificationAssistantRequest;
use App\Domain\Clarification\Data\ClarificationAssistantResult;

interface ClarificationAssistant
{
    public function rankAndReword(ClarificationAssistantRequest $request): ClarificationAssistantResult;
}
