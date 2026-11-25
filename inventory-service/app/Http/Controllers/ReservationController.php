<?php

namespace App\Http\Controllers;

use App\Services\InventoryService;
use App\Validators\ReservationValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    /**
     * @param InventoryService $inventoryService
     * @param ReservationValidator $reservationValidator
     */
    public function __construct(
        protected InventoryService $inventoryService,
        protected ReservationValidator $reservationValidator
    ) {
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        // 1. Chỉ lấy field cần
        $data = $request->only(['ref', 'part_id', 'qty']);

        // 2. Validate dữ liệu
        $validator = $this->reservationValidator->storeValidator($data);
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first(), 'error_code' => 'validation_failed'], 422);
        }

        // 3. Gọi Service xử lý
        $reservation = $this->inventoryService->reserve($data['ref'], (int) $data['part_id'], (int) $data['qty']);

        // 4. Phản hồi: 201 khi giữ mới, 200 khi gửi lại cùng ref
        return response()->json(['data' => $reservation], $reservation->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * @param string $ref
     * @return JsonResponse
     */
    public function show(string $ref): JsonResponse
    {
        return response()->json(['data' => $this->inventoryService->find($ref)]);
    }

    /**
     * @param string $ref
     * @return JsonResponse
     */
    public function release(string $ref): JsonResponse
    {
        return response()->json(['data' => $this->inventoryService->release($ref)]);
    }

    /**
     * @param string $ref
     * @return JsonResponse
     */
    public function consume(string $ref): JsonResponse
    {
        return response()->json(['data' => $this->inventoryService->consume($ref)]);
    }
}
