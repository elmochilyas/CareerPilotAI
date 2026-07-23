<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Profile\Actions\CreateProfileItemAction;
use App\Domain\Profile\Actions\DeleteProfileItemAction;
use App\Domain\Profile\Actions\ReorderProfileItemsAction;
use App\Domain\Profile\Actions\ShowProfileAction;
use App\Domain\Profile\Actions\UpdateProfileAction;
use App\Domain\Profile\Actions\UpdateProfileItemAction;
use App\Domain\Profile\Data\ProfileData;
use App\Domain\Profile\Data\ProfileItemData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Profile\ReorderProfileItemsRequest;
use App\Http\Requests\Api\V1\Profile\StoreProfileItemRequest;
use App\Http\Requests\Api\V1\Profile\UpdateProfileItemRequest;
use App\Http\Requests\Api\V1\Profile\UpsertProfileRequest;
use App\Http\Resources\Api\V1\ProfileItemResource;
use App\Http\Resources\Api\V1\ProfileResource;
use App\Models\ProfileItem;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ProfileController extends Controller
{
    public function show(Request $request, ShowProfileAction $action): JsonResponse
    {
        return ProfileResource::make($action->execute($this->user($request)))->response();
    }

    public function update(UpsertProfileRequest $request, UpdateProfileAction $action): JsonResponse
    {
        return ProfileResource::make($action->execute($this->user($request), ProfileData::from($request->validated())))->response();
    }

    public function storeItem(StoreProfileItemRequest $request, CreateProfileItemAction $action): JsonResponse
    {
        Gate::authorize('create', ProfileItem::class);

        return ProfileItemResource::make($action->execute($this->user($request), ProfileItemData::from($request->validated())))->response()->setStatusCode(201);
    }

    public function updateItem(UpdateProfileItemRequest $request, int $profileItem, UpdateProfileItemAction $action): JsonResponse
    {
        $item = $this->ownedItem($request, $profileItem);
        Gate::authorize('update', $item);

        return ProfileItemResource::make($action->execute($item, ProfileItemData::from($request->validated())))->response();
    }

    public function destroyItem(Request $request, int $profileItem, DeleteProfileItemAction $action): Response
    {
        $item = $this->ownedItem($request, $profileItem);
        Gate::authorize('delete', $item);
        $action->execute($item);

        return response()->noContent();
    }

    public function reorderItems(ReorderProfileItemsRequest $request, ReorderProfileItemsAction $action): AnonymousResourceCollection
    {
        return ProfileItemResource::collection($action->execute($this->user($request), $request->string('type')->toString(), $request->array('item_ids')));
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    private function ownedItem(Request $request, int $id): ProfileItem
    {
        return ProfileItem::query()->whereKey($id)->whereHas('candidateProfile', fn ($query) => $query->where('user_id', $this->user($request)->id))->firstOrFail();
    }
}
