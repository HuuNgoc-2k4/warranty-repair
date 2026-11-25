<?php

namespace App\Http\Controllers;

use App\Services\WarrantyService;
use Illuminate\Http\JsonResponse;

class WarrantyController extends Controller
{
    /**
     * @param WarrantyService $warrantyService
     */
    public function __construct(protected WarrantyService $warrantyService)
    {
    }

    /**
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->warrantyService->listDevices()]);
    }

    /**
     * @param string $serial
     * @return JsonResponse
     */
    public function show(string $serial): JsonResponse
    {
        return response()->json(['data' => $this->warrantyService->check($serial)]);
    }
}
