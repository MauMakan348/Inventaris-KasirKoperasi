<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    /**
     * Checkout a multi-item cart.
     */
    public function store(CheckoutRequest $request): JsonResponse
    {
        $order = $this->orderService->checkout($request->user(), $request->validated());

        return (new OrderResource($order))->response()->setStatusCode(201);
    }

    /**
     * List the orders visible to the authenticated user.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return OrderResource::collection($this->orderService->listForViewer($request->user()));
    }

    /**
     * Show one order with its item snapshots.
     */
    public function show(Request $request, int $id): OrderResource
    {
        return new OrderResource($this->orderService->showForViewer($id, $request->user()));
    }
}
