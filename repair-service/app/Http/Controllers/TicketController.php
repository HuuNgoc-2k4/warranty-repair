<?php

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Services\TicketService;
use App\Validators\TicketValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    /**
     * @param TicketService $ticketService
     * @param TicketValidator $ticketValidator
     */
    public function __construct(
        protected TicketService $ticketService,
        protected TicketValidator $ticketValidator
    ) {
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // 1. Validate bộ lọc
        $data = $request->only(['status']);
        $validator = $this->ticketValidator->indexValidator($data);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->first());
        }

        // 2. Gọi Service và phản hồi
        return response()->json(['data' => $this->ticketService->list($data['status'] ?? null)]);
    }

    /**
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->ticketService->get($id)]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        // 1. Gom dữ liệu: Idempotency-Key nằm ở header, qty mặc định 1
        $data = $request->only(['serial', 'customer', 'phone', 'issue', 'part_id', 'qty']);
        $data['qty'] = $data['qty'] ?? 1;
        $key = (string) $request->header('Idempotency-Key');

        // 2. Validate dữ liệu
        $validator = $this->ticketValidator->storeValidator($data + ['idempotency_key' => $key]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->first());
        }
        $data['part_id'] = (int) $data['part_id'];
        $data['qty'] = (int) $data['qty'];

        // 3. Gọi Service xử lý
        $result = $this->ticketService->create($data, $key, $request->header('X-Simulate'));
        $ticket = $result['ticket'];

        // 4. Phản hồi: 200 gửi lại, 202 chưa rõ kết quả (đợi đối soát), 201 tạo mới
        if (!$result['created']) {
            return response()->json(['data' => $ticket], 200);
        }
        if ($ticket->status === TicketStatus::PENDING->value) {
            return response()->json([
                'message' => 'Chưa xác định được kết quả giữ linh kiện, phiếu sẽ được đối soát lại.',
                'data' => $ticket
            ], 202);
        }

        return response()->json(['data' => $ticket], 201);
    }

    /**
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function changeStatus(Request $request, int $id): JsonResponse
    {
        // 1. Validate dữ liệu
        $data = $request->only(['status']);
        $validator = $this->ticketValidator->statusValidator($data);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->first());
        }

        // 2. Gọi Service và phản hồi
        return response()->json(['data' => $this->ticketService->changeStatus($id, $data['status'], $request->header('X-Simulate'))]);
    }

    /**
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        return response()->json(['data' => $this->ticketService->cancel($id, $request->header('X-Simulate'))]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function reconcile(Request $request): JsonResponse
    {
        // 1. Validate dữ liệu
        $data = $request->only(['older_than']);
        $validator = $this->ticketValidator->reconcileValidator($data);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->first());
        }

        // 2. Gọi Service và phản hồi
        $olderThan = (int) ($data['older_than'] ?? TicketService::RECONCILE_AFTER_SECONDS);

        return response()->json(['data' => $this->ticketService->reconcile($olderThan)]);
    }

    /**
     * @param string $message
     * @return JsonResponse
     */
    protected function validationError(string $message): JsonResponse
    {
        return response()->json(['message' => $message, 'error_code' => 'validation_failed'], 422);
    }
}
