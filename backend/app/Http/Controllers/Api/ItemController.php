<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Http\Resources\ItemResource;
use App\Services\ItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ItemController extends Controller
{
    public function __construct(private readonly ItemService $itemService) {}

    /**
     * List every item with its current stock.
     */
    public function index(): AnonymousResourceCollection
    {
        return ItemResource::collection($this->itemService->list());
    }

    /**
     * Show one item.
     */
    public function show(int $id): ItemResource
    {
        return new ItemResource($this->itemService->show($id));
    }

    /**
     * Create a new item.
     */
    public function store(StoreItemRequest $request): JsonResponse
    {
        $item = $this->itemService->create($request->validated(), $request->user());

        return (new ItemResource($item))->response()->setStatusCode(201);
    }

    /**
     * Update the editable attributes of an item.
     */
    public function update(UpdateItemRequest $request, int $id): ItemResource
    {
        $item = $this->itemService->show($id);

        return new ItemResource($this->itemService->update($item, $request->validated()));
    }
}
