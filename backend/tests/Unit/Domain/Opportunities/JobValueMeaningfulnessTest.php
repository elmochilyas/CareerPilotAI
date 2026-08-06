<?php

use App\Domain\Opportunities\Services\JobValueMeaningfulness;
use Tests\TestCase;

uses(TestCase::class);

it('treats the literal string "null" as a non-meaningful string', function () {
    expect(JobValueMeaningfulness::isMeaningfulString('null'))->toBeFalse()
        ->and(JobValueMeaningfulness::isMeaningfulString(' NULL '))->toBeFalse();
});

it('treats the literal string "null" as a non-meaningful date', function () {
    expect(JobValueMeaningfulness::isMeaningfulDate('null'))->toBeFalse();
});
