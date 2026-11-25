<?php

namespace App\Repositories;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Collection;

class TicketRepository
{
    /**
     * @param int $id
     * @return Ticket|null
     */
    public function findById(int $id): ?Ticket
    {
        return Ticket::query()->find($id);
    }

    /**
     * Khóa dòng để các thao tác đổi trạng thái/hủy chạy song song phải xếp hàng.
     *
     * @param int $id
     * @return Ticket|null
     */
    public function findByIdForUpdate(int $id): ?Ticket
    {
        return Ticket::query()->where('id', $id)->lockForUpdate()->first();
    }

    /**
     * @param string $key
     * @return Ticket|null
     */
    public function findByKey(string $key): ?Ticket
    {
        return Ticket::query()->where('idempotency_key', $key)->first();
    }

    /**
     * @param array $data
     * @return Ticket
     */
    public function create(array $data): Ticket
    {
        return Ticket::query()->create($data);
    }

    /**
     * @param string|null $status
     * @param int $limit
     * @return Collection
     */
    public function list(?string $status, int $limit): Collection
    {
        return Ticket::query()
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Phiếu PENDING đã quá lâu: kết quả giữ linh kiện của chúng cần được đối soát.
     *
     * @param int $olderThanSeconds
     * @return Collection
     */
    public function pendingOlderThan(int $olderThanSeconds): Collection
    {
        return Ticket::query()
            ->where('status', TicketStatus::PENDING->value)
            ->where('updated_at', '<=', now()->subSeconds($olderThanSeconds))
            ->orderBy('id')
            ->get();
    }

    /**
     * Đổi trạng thái có điều kiện (chỉ khi còn ở trạng thái cũ) để không ghi đè kết quả của luồng khác.
     *
     * @param Ticket $ticket
     * @param string $from
     * @param string $to
     * @param string|null $failureReason
     * @return bool true nếu đã đổi
     */
    public function transition(Ticket $ticket, string $from, string $to, ?string $failureReason = null): bool
    {
        $changed = Ticket::query()
            ->where('id', $ticket->id)
            ->where('status', $from)
            ->update(['status' => $to, 'failure_reason' => $failureReason, 'updated_at' => now()]);

        return $changed === 1;
    }

    /**
     * @param Ticket $ticket
     * @param string|null $from
     * @param string $to
     * @param string|null $note
     * @return void
     */
    public function addEvent(Ticket $ticket, ?string $from, string $to, ?string $note = null): void
    {
        $ticket->events()->create(['from_status' => $from, 'to_status' => $to, 'note' => $note]);
    }
}
