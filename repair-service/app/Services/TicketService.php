<?php

namespace App\Services;

use App\Clients\InventoryClient;
use App\Clients\WarrantyClient;
use App\Enums\TicketStatus;
use App\Exceptions\BusinessException;
use App\Exceptions\RemoteUnavailableException;
use App\Models\Ticket;
use App\Repositories\TicketRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class TicketService
{
    public const RECONCILE_AFTER_SECONDS = 60;

    public const LIST_LIMIT = 100;

    protected const REF_PREFIX = 'T';

    protected const FAILURE_INVENTORY_UNAVAILABLE = 'inventory_unavailable';

    /**
     * Các bước đi tiếp hợp lệ của vòng đời phiếu (hủy xử lý riêng ở cancel()).
     */
    protected const NEXT_STATUS = [
        'RECEIVED' => 'REPAIRING',
        'REPAIRING' => 'DONE',
        'DONE' => 'RETURNED'
    ];

    /**
     * @param TicketRepository $tickets
     * @param WarrantyClient $warranty
     * @param InventoryClient $inventory
     */
    public function __construct(
        protected TicketRepository $tickets,
        protected WarrantyClient $warranty,
        protected InventoryClient $inventory
    ) {
    }

    /**
     * @param string|null $status
     * @return Collection
     */
    public function list(?string $status): Collection
    {
        return $this->tickets->list($status, self::LIST_LIMIT);
    }

    /**
     * @param int $id
     * @return Ticket
     * @throws BusinessException
     */
    public function get(int $id): Ticket
    {
        $ticket = $this->tickets->findById($id);
        if (!$ticket) {
            throw new BusinessException('Không tìm thấy phiếu.', 'ticket_not_found', 404);
        }

        return $ticket->load('events');
    }

    /**
     * Tạo phiếu tiếp nhận: kiểm tra bảo hành → lưu PENDING → giữ linh kiện → RECEIVED.
     * Idempotent theo Idempotency-Key: gửi lại trả phiếu cũ, không tạo phiếu hay giữ kho lần nữa.
     *
     * @param array $data serial, customer, phone, issue, part_id, qty
     * @param string $key Idempotency-Key
     * @param string|null $simulate X-Simulate chuyển tiếp sang Inventory
     * @return array{ticket: Ticket, created: bool}
     * @throws BusinessException
     * @throws RemoteUnavailableException
     */
    public function create(array $data, string $key, ?string $simulate): array
    {
        $hash = $this->hashRequest($data);
        $existing = $this->tickets->findByKey($key);
        if ($existing) {
            return $this->replay($existing, $hash);
        }

        // Bước chỉ đọc nên chưa lưu gì: Warranty lỗi thì client gửi lại bình thường
        try {
            $warranty = $this->warranty->check($data['serial']);
        } catch (BusinessException $e) {
            if ($e->getErrorCode() === 'device_not_found') {
                throw new BusinessException('Serial chưa được đăng ký bảo hành.', 'device_not_found', 422);
            }
            throw $e;
        }

        try {
            $ticket = $this->tickets->create($data + [
                'in_warranty' => (bool) $warranty['valid'],
                'status' => TicketStatus::PENDING->value,
                'idempotency_key' => $key,
                'request_hash' => $hash
            ]);
        } catch (UniqueConstraintViolationException) {
            // Cùng key gửi song song: bản kia đã tạo phiếu
            return $this->replay($this->tickets->findByKey($key), $hash);
        }
        $this->tickets->addEvent($ticket, null, TicketStatus::PENDING->value, 'Tạo phiếu');

        return ['ticket' => $this->reserveParts($ticket, $simulate), 'created' => true];
    }

    /**
     * Đổi trạng thái theo vòng đời; sang REPAIRING thì xác nhận dùng linh kiện ở Inventory.
     *
     * @param int $id
     * @param string $to
     * @param string|null $simulate
     * @return Ticket
     * @throws BusinessException
     * @throws RemoteUnavailableException
     */
    public function changeStatus(int $id, string $to, ?string $simulate): Ticket
    {
        return DB::transaction(function () use ($id, $to, $simulate) {
            $ticket = $this->lockTicket($id);
            if ($ticket->status === $to) {
                return $ticket;
            }
            if ((self::NEXT_STATUS[$ticket->status] ?? null) !== $to) {
                throw new BusinessException("Không thể chuyển phiếu từ {$ticket->status} sang {$to}.", 'invalid_transition', 422);
            }

            // Lỗi chắc chắn làm rollback, phiếu giữ nguyên; consume idempotent nên client gửi lại an toàn
            if ($to === TicketStatus::REPAIRING->value) {
                $this->consumeParts($ticket, $simulate);
            }

            return $this->move($ticket, $to);
        });
    }

    /**
     * Hủy phiếu: trả linh kiện về kho rồi chuyển CANCELLED. Chỉ hủy được khi mới tiếp nhận.
     *
     * @param int $id
     * @param string|null $simulate
     * @return Ticket
     * @throws BusinessException
     * @throws RemoteUnavailableException
     */
    public function cancel(int $id, ?string $simulate): Ticket
    {
        return DB::transaction(function () use ($id, $simulate) {
            $ticket = $this->lockTicket($id);
            if ($ticket->status === TicketStatus::CANCELLED->value) {
                return $ticket;
            }
            if ($ticket->status === TicketStatus::PENDING->value) {
                throw new BusinessException('Phiếu đang chờ xử lý, chưa thể hủy.', 'ticket_pending', 409);
            }
            if ($ticket->status !== TicketStatus::RECEIVED->value) {
                throw new BusinessException('Phiếu đã vào quy trình sửa chữa hoặc đã đóng, không thể hủy.', 'cancel_not_allowed', 409);
            }

            $this->releaseParts($ticket, $simulate);

            return $this->move($ticket, TicketStatus::CANCELLED->value, 'Hủy phiếu, đã trả linh kiện');
        });
    }

    /**
     * Đối soát các phiếu PENDING quá hạn bằng cách tra lại Inventory.
     *
     * @param int $olderThanSeconds
     * @return array{checked: int, received: int, failed: int, pending: int}
     */
    public function reconcile(int $olderThanSeconds): array
    {
        $summary = ['checked' => 0, 'received' => 0, 'failed' => 0, 'pending' => 0];
        foreach ($this->tickets->pendingOlderThan($olderThanSeconds) as $candidate) {
            $status = DB::transaction(function () use ($candidate) {
                $ticket = $this->tickets->findByIdForUpdate($candidate->id);
                if ($ticket->status !== TicketStatus::PENDING->value) {
                    return $ticket->status;
                }

                return $this->resolveReservation($ticket, null)->status;
            });

            $summary['checked']++;
            match ($status) {
                TicketStatus::RECEIVED->value => $summary['received']++,
                TicketStatus::FAILED->value => $summary['failed']++,
                default => $summary['pending']++
            };
        }

        return $summary;
    }

    /**
     * Giữ linh kiện cho phiếu mới. Từ chối rõ ràng → FAILED ngay; chưa rõ kết quả → tra lại rồi mới quyết định.
     *
     * @param Ticket $ticket
     * @param string|null $simulate
     * @return Ticket
     * @throws BusinessException
     * @throws RemoteUnavailableException
     */
    protected function reserveParts(Ticket $ticket, ?string $simulate): Ticket
    {
        try {
            $this->inventory->reserve($this->refOf($ticket), $ticket->part_id, $ticket->qty, $simulate);

            return $this->received($ticket);
        } catch (BusinessException $e) {
            $this->failed($ticket, $e->getErrorCode(), $e->getMessage());
            throw $e;
        } catch (RemoteUnavailableException) {
            $ticket = $this->resolveReservation($ticket, $simulate);
            if ($ticket->status === TicketStatus::FAILED->value) {
                throw $this->failureException($ticket);
            }

            return $ticket;
        }
    }

    /**
     * Xử lý trạng thái chưa rõ của lệnh giữ linh kiện: tra lượt giữ; chưa có thì gửi lại cùng ref (idempotent);
     * vẫn lỗi thì tra lần nữa. Chỉ FAILED khi chắc chắn kho không có lượt giữ; không tra được thì để PENDING cho đối soát.
     *
     * @param Ticket $ticket
     * @param string|null $simulate
     * @return Ticket
     */
    protected function resolveReservation(Ticket $ticket, ?string $simulate): Ticket
    {
        $ref = $this->refOf($ticket);

        try {
            $reservation = $this->findReservation($ref);
            if (!$reservation) {
                try {
                    $reservation = $this->inventory->reserve($ref, $ticket->part_id, $ticket->qty, $simulate);
                } catch (RemoteUnavailableException) {
                    $reservation = $this->findReservation($ref);
                    if (!$reservation) {
                        return $this->failed($ticket, self::FAILURE_INVENTORY_UNAVAILABLE, 'Kho không phản hồi, chưa giữ được linh kiện.');
                    }
                }
            }
        } catch (BusinessException $e) {
            return $this->failed($ticket, $e->getErrorCode(), $e->getMessage());
        } catch (RemoteUnavailableException) {
            return $ticket->refresh();
        }

        if (($reservation['status'] ?? null) === 'RESERVED') {
            return $this->received($ticket);
        }

        return $this->failed($ticket, 'reservation_'.strtolower((string) ($reservation['status'] ?? 'invalid')), 'Lượt giữ linh kiện không còn hiệu lực.');
    }

    /**
     * Trả linh kiện; timeout thì tra lượt giữ: nếu thực ra đã được trả thì coi như thành công.
     *
     * @param Ticket $ticket
     * @param string|null $simulate
     * @return void
     * @throws BusinessException
     * @throws RemoteUnavailableException
     */
    protected function releaseParts(Ticket $ticket, ?string $simulate): void
    {
        $ref = $this->refOf($ticket);
        try {
            $this->inventory->release($ref, $simulate);
        } catch (RemoteUnavailableException $e) {
            $reservation = $this->findReservation($ref);
            if (($reservation['status'] ?? null) !== 'RELEASED') {
                throw $e;
            }
        }
    }

    /**
     * Xác nhận dùng linh kiện; timeout thì tra lượt giữ: nếu thực ra đã CONSUMED thì coi như thành công,
     * nếu không thì để phiếu giữ nguyên (không đi tiếp khi chưa chắc kho đã ghi nhận).
     *
     * @param Ticket $ticket
     * @param string|null $simulate
     * @return void
     * @throws BusinessException
     * @throws RemoteUnavailableException
     */
    protected function consumeParts(Ticket $ticket, ?string $simulate): void
    {
        $ref = $this->refOf($ticket);
        try {
            $this->inventory->consume($ref, $simulate);
        } catch (RemoteUnavailableException $e) {
            $reservation = $this->findReservation($ref);
            if (($reservation['status'] ?? null) !== 'CONSUMED') {
                throw $e;
            }
        }
    }

    /**
     * Tra lượt giữ linh kiện; null nghĩa là Inventory xác nhận chưa có.
     *
     * @param string $ref
     * @return array|null
     * @throws BusinessException
     * @throws RemoteUnavailableException
     */
    protected function findReservation(string $ref): ?array
    {
        try {
            return $this->inventory->find($ref);
        } catch (BusinessException $e) {
            if ($e->getErrorCode() === 'reservation_not_found') {
                return null;
            }
            throw $e;
        }
    }

    /**
     * @param Ticket $ticket
     * @param string $hash
     * @return array{ticket: Ticket, created: bool}
     * @throws BusinessException
     */
    protected function replay(Ticket $ticket, string $hash): array
    {
        if ($ticket->request_hash !== $hash) {
            throw new BusinessException('Idempotency-Key đã được dùng cho một yêu cầu khác.', 'idempotency_key_conflict', 409);
        }

        return ['ticket' => $ticket, 'created' => false];
    }

    /**
     * @param int $id
     * @return Ticket
     * @throws BusinessException
     */
    protected function lockTicket(int $id): Ticket
    {
        $ticket = $this->tickets->findByIdForUpdate($id);
        if (!$ticket) {
            throw new BusinessException('Không tìm thấy phiếu.', 'ticket_not_found', 404);
        }

        return $ticket;
    }

    /**
     * @param Ticket $ticket
     * @param string $to
     * @param string|null $note
     * @return Ticket
     */
    protected function move(Ticket $ticket, string $to, ?string $note = null): Ticket
    {
        $from = $ticket->status;
        if ($this->tickets->transition($ticket, $from, $to)) {
            $this->tickets->addEvent($ticket, $from, $to, $note);
        }

        return $ticket->refresh();
    }

    /**
     * @param Ticket $ticket
     * @return Ticket
     */
    protected function received(Ticket $ticket): Ticket
    {
        if ($this->tickets->transition($ticket, TicketStatus::PENDING->value, TicketStatus::RECEIVED->value)) {
            $this->tickets->addEvent($ticket, TicketStatus::PENDING->value, TicketStatus::RECEIVED->value, 'Đã giữ linh kiện');
        }

        return $ticket->refresh();
    }

    /**
     * @param Ticket $ticket
     * @param string $reason
     * @param string $note
     * @return Ticket
     */
    protected function failed(Ticket $ticket, string $reason, string $note): Ticket
    {
        if ($this->tickets->transition($ticket, TicketStatus::PENDING->value, TicketStatus::FAILED->value, $reason)) {
            $this->tickets->addEvent($ticket, TicketStatus::PENDING->value, TicketStatus::FAILED->value, $note);
        }

        return $ticket->refresh();
    }

    /**
     * @param Ticket $ticket
     * @return BusinessException|RemoteUnavailableException
     */
    protected function failureException(Ticket $ticket): BusinessException|RemoteUnavailableException
    {
        if ($ticket->failure_reason === self::FAILURE_INVENTORY_UNAVAILABLE) {
            return new RemoteUnavailableException('inventory');
        }

        return new BusinessException("Phiếu #{$ticket->id} không giữ được linh kiện.", (string) $ticket->failure_reason, 409);
    }

    /**
     * @param Ticket $ticket
     * @return string
     */
    protected function refOf(Ticket $ticket): string
    {
        return self::REF_PREFIX.$ticket->id;
    }

    /**
     * Băm nội dung yêu cầu để phát hiện cùng Idempotency-Key nhưng khác dữ liệu.
     *
     * @param array $data
     * @return string
     */
    protected function hashRequest(array $data): string
    {
        ksort($data);

        return hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}
