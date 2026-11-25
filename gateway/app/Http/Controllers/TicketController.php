<?php

namespace App\Http\Controllers;

use App\Clients\ServiceGateway;
use App\Exceptions\ServiceException;
use App\Validators\TicketValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TicketController extends Controller
{
    /**
     * @param ServiceGateway $services
     * @param TicketValidator $ticketValidator
     */
    public function __construct(
        protected ServiceGateway $services,
        protected TicketValidator $ticketValidator
    ) {
    }

    /**
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        // 1. Chỉ nhận bộ lọc hợp lệ
        $status = $request->query('status');
        if ($this->ticketValidator->filterValidator(['status' => $status])->fails()) {
            $status = null;
        }

        // 2. Lấy danh sách; dịch vụ lỗi thì vẫn hiện trang kèm thông báo
        try {
            $tickets = $this->services->tickets($status);
        } catch (ServiceException $e) {
            $tickets = [];
            session()->now('error', $e->getMessage());
        }

        return view('tickets.index', ['tickets' => $tickets, 'status' => $status]);
    }

    /**
     * @return View
     */
    public function create(): View
    {
        // Danh mục thiết bị/linh kiện lỗi thì form vẫn hiện nhưng danh sách trống
        try {
            $devices = $this->services->devices();
            $parts = $this->services->parts();
        } catch (ServiceException $e) {
            $devices = $parts = [];
            session()->now('error', $e->getMessage());
        }

        return view('tickets.create', [
            'devices' => $devices,
            'parts' => $parts,
            'idempotencyKey' => old('idempotency_key', (string) Str::uuid())
        ]);
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        // 1. Validate dữ liệu
        $input = $request->only(['idempotency_key', 'serial', 'customer', 'phone', 'issue', 'part_id', 'qty', 'simulate']);
        $validator = $this->ticketValidator->storeValidator($input);
        if ($validator->fails()) {
            return back()->withInput()->withErrors($validator);
        }

        // 2. Gọi Repair tạo phiếu
        try {
            $result = $this->services->createTicket(
                [
                    'serial' => $input['serial'],
                    'customer' => $input['customer'],
                    'phone' => $input['phone'],
                    'issue' => $input['issue'],
                    'part_id' => (int) $input['part_id'],
                    'qty' => (int) $input['qty']
                ],
                $input['idempotency_key'],
                $input['simulate'] ?? null
            );
        } catch (ServiceException $e) {
            // Chỉ giữ key khi không biết Repair đã nhận chưa (mất kết nối); lỗi rõ ràng thì lần gửi sau dùng key mới
            $keepKey = $e->getErrorCode() === ServiceException::UNAVAILABLE;

            return back()->withInput($keepKey ? $input : array_diff_key($input, ['idempotency_key' => 1]))->with('error', $e->getMessage());
        }

        // 3. Phản hồi: 202 nghĩa là chưa rõ kết quả giữ linh kiện, đang chờ đối soát
        $redirect = redirect()->route('tickets.show', $result['data']['id']);
        if ($result['status'] === 202) {
            return $redirect->with('warning', (string) $result['message']);
        }

        return $redirect->with('success', 'Đã tạo phiếu #'.$result['data']['id'].'.');
    }

    /**
     * @param int $id
     * @return View|RedirectResponse
     */
    public function show(int $id): View|RedirectResponse
    {
        try {
            $ticket = $this->services->ticket($id);
        } catch (ServiceException $e) {
            return redirect()->route('tickets.index')->with('error', $e->getMessage());
        }

        return view('tickets.show', ['ticket' => $ticket]);
    }

    /**
     * @param Request $request
     * @param int $id
     * @return RedirectResponse
     */
    public function changeStatus(Request $request, int $id): RedirectResponse
    {
        // 1. Validate dữ liệu
        $input = $request->only(['status', 'simulate']);
        $validator = $this->ticketValidator->statusValidator($input);
        if ($validator->fails()) {
            return back()->with('error', $validator->errors()->first());
        }

        // 2. Gọi Repair đổi trạng thái và phản hồi
        try {
            $this->services->changeStatus($id, $input['status'], $input['simulate'] ?? null);
        } catch (ServiceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã cập nhật trạng thái.');
    }

    /**
     * @param Request $request
     * @param int $id
     * @return RedirectResponse
     */
    public function cancel(Request $request, int $id): RedirectResponse
    {
        // 1. Validate dữ liệu
        $input = $request->only(['simulate']);
        $validator = $this->ticketValidator->simulateValidator($input);
        if ($validator->fails()) {
            return back()->with('error', $validator->errors()->first());
        }

        // 2. Gọi Repair hủy phiếu và phản hồi
        try {
            $this->services->cancel($id, $input['simulate'] ?? null);
        } catch (ServiceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã hủy phiếu và trả linh kiện về kho.');
    }

    /**
     * @return RedirectResponse
     */
    public function reconcile(): RedirectResponse
    {
        try {
            $summary = $this->services->reconcile();
        } catch (ServiceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', sprintf(
            'Đã đối soát %d phiếu: %d tiếp nhận, %d thất bại, %d vẫn chờ.',
            $summary['checked'],
            $summary['received'],
            $summary['failed'],
            $summary['pending']
        ));
    }
}
