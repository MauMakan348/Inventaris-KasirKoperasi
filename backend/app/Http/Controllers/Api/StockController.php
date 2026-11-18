<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RestockRequest;
use App\Http\Requests\StockOpnameRequest;
use App\Http\Resources\ItemResource;
use App\Http\Resources\StockMovementResource;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockController extends Controller
{
    public function __construct(private readonly StockService $stockService) {}

    /**
     * Add incoming stock to an item.
     */
    public function restock(RestockRequest $request, int $id): JsonResponse
    {
        $result = $this->stockService->restock(
            $id,
            (int) $request->validated('quantity'),
            $request->validated('note'),
            $request->user(),
        );

        return response()->json([
            'data' => [
                'item' => new ItemResource($result['item']),
                'movement' => new StockMovementResource($result['movement']),
            ],
        ]);
    }

    /**
     * Set the stock of an item to the counted physical amount.
     */
    public function opname(StockOpnameRequest $request, int $id): JsonResponse
    {
        $result = $this->stockService->opname(
            $id,
            (int) $request->validated('actual_stock'),
            $request->validated('note'),
            $request->user(),
        );

        return response()->json([
            'data' => [
                'item' => new ItemResource($result['item']),
                'movement' => $result['movement'] === null
                    ? null
                    : new StockMovementResource($result['movement']),
            ],
        ]);
    }

    /**
     * List the stock movement history of an item.
     */
    public function movements(int $id): AnonymousResourceCollection
    {
        return StockMovementResource::collection($this->stockService->movements($id));
    }
}
