<?php

namespace App\Http\Controllers;

use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;

class PartController extends Controller
{
    /**
     * @param InventoryService $inventoryService
     */
    public function __construct(protected InventoryService $inventoryService)
    {
    }

    /**
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->inventoryService->listParts()]);
    }
}
