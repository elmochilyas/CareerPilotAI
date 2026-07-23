<?php

use App\Domain\Profile\Enums\ProfileItemType;
use App\Models\CandidateProfile;
use App\Models\ProfileItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('api', 'profile-items');

it('creates every supported profile item type', function (string $type) {
    $user = User::factory()->create();
    $this->actingAs($user)->postJson('/api/v1/profile/items', ['type' => $type, 'title' => 'Example'])->assertCreated()->assertJsonPath('data.type', $type);
})->with(array_map(fn (ProfileItemType $type): string => $type->value, ProfileItemType::cases()));

it('rejects invalid types and date ranges', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->postJson('/api/v1/profile/items', ['type' => 'invalid', 'title' => 'Example'])->assertUnprocessable()->assertJsonValidationErrors('type');
    $this->actingAs($user)->postJson('/api/v1/profile/items', ['type' => 'experience', 'title' => 'Example', 'start_date' => '2024-01-01', 'end_date' => '2020-01-01'])->assertUnprocessable()->assertJsonValidationErrors('end_date');
});

it('updates and deletes an owned item', function () {
    $item = ProfileItem::factory()->create();
    $this->actingAs($item->candidateProfile->user)->patchJson("/api/v1/profile/items/{$item->id}", ['title' => 'Updated'])->assertOk()->assertJsonPath('data.title', 'Updated');
    $this->actingAs($item->candidateProfile->user)->deleteJson("/api/v1/profile/items/{$item->id}")->assertNoContent();
    $this->assertDatabaseMissing('profile_items', ['id' => $item->id]);
});

it('returns 404 for another users item without changing it', function () {
    $owner = User::factory()->create();
    $item = ProfileItem::factory()->for(CandidateProfile::factory()->for($owner))->create();
    $other = User::factory()->create();
    $this->actingAs($other)->patchJson("/api/v1/profile/items/{$item->id}", ['title' => 'Stolen'])->assertNotFound();
    $this->actingAs($other)->deleteJson("/api/v1/profile/items/{$item->id}")->assertNotFound();
    expect($item->fresh()->title)->not->toBe('Stolen');
});

it('reorders atomically and rejects foreign or incomplete lists', function () {
    $profile = CandidateProfile::factory()->create();
    $first = ProfileItem::factory()->for($profile)->create(['display_order' => 0]);
    $second = ProfileItem::factory()->for($profile)->create(['display_order' => 1]);
    $this->actingAs($profile->user)->patchJson('/api/v1/profile/items/reorder', ['type' => 'experience', 'item_ids' => [$second->id, $first->id]])->assertOk();
    expect($second->fresh()->display_order)->toBe(0);
    $this->actingAs($profile->user)->patchJson('/api/v1/profile/items/reorder', ['type' => 'experience', 'item_ids' => [$first->id]])->assertUnprocessable()->assertJsonPath('code', 'profile_item_reorder_invalid');
    expect($second->fresh()->display_order)->toBe(0);
});
