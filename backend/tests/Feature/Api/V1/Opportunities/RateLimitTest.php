<?php

use App\Http\Controllers\Api\V1\JobOpportunityConfirmedController;
use App\Models\CandidateProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class)->group('api', 'opportunities', 'rate-limiting');

beforeEach(function () {
    Queue::fake();
    $this->user = User::factory()->create();
    CandidateProfile::factory()->create(['user_id' => $this->user->id]);
});

it('has throttle middleware on store route', function () {
    $route = Route::getRoutes()->getByAction('App\Http\Controllers\Api\V1\JobOpportunityIngestionController@store');
    expect($route)->not->toBeNull();
    $middleware = $route->gatherMiddleware();
    expect($middleware)->toContain('throttle:10,1');
});

it('has throttle middleware on retry route', function () {
    $route = Route::getRoutes()->getByAction('App\Http\Controllers\Api\V1\JobOpportunityIngestionController@retry');
    expect($route)->not->toBeNull();
    $middleware = $route->gatherMiddleware();
    expect($middleware)->toContain('throttle:5,1');
});

it('has throttle middleware on preview route', function () {
    $route = Route::getRoutes()->getByAction('App\Http\Controllers\Api\V1\JobOpportunityConfirmedController@preview');
    expect($route)->not->toBeNull();
    $middleware = $route->gatherMiddleware();
    expect($middleware)->toContain('throttle:10,1');
});

it('has throttle middleware on confirm route', function () {
    $route = Route::getRoutes()->getByAction(JobOpportunityConfirmedController::class.'@confirm');
    expect($route)->not->toBeNull();
    $middleware = $route->gatherMiddleware();
    expect($middleware)->toContain('throttle:100,1');
});
