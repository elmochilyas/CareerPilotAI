<?php

namespace App\Domain\Matching\Services\Contracts;

use App\Domain\Matching\Data\ClassifierRequest;
use App\Domain\Matching\Data\ClassifierResult;

interface RequirementClassifier
{
    public function classify(ClassifierRequest $request): ClassifierResult;
}
