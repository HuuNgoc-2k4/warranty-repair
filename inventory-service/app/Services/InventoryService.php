<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Exceptions\BusinessException;
use App\Models\Reservation;
use App\Repositories\PartRepository;
use App\Repositories\ReservationRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * @param PartRepository $parts
     * @param ReservationRepository $reservations
     */
    public function __construct(
        protected PartRepository $parts,
        protected ReservationRepository $reservations
    ) {
    }

    /**
     * @return Collection
     */
    public function listParts(): Collection
    {
        return $this->parts->all();
    }

    /**
     * Giữ linh kiện; idempotent theo ref (gửi lại trả lượt giữ cũ, không trừ kho lần nữa).
     *
     * @param string $ref
     * @param int $partId
     * @param int $qty
     * @return Reservation
     * @throws BusinessException
     */
    public function reserve(string $ref, int $partId, int $qty): Reservation
    {
        $existing = $this->reservations->findByRef($ref);
        if ($existing) {
            return $existing;
        }
        if (!$this->parts->exists($partId)) {
            throw new BusinessException('Linh kiện không tồn tại.', 'part_not_found', 404);
        }

        try {
            return DB::transaction(function () use ($ref, $partId, $qty) {
                if (!$this->parts->decrementIfAvailable($partId, $qty)) {
                    throw new BusinessException('Không đủ linh kiện trong kho.', 'out_of_stock', 409);
                }

                return $this->reservations->create([
                    'ref' => $ref,
                    'part_id' => $partId,
                    'qty' => $qty,
                    'status' => ReservationStatus::RESERVED->value,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            // Cùng ref chạy song song: bản kia đã giữ xong, transaction này đã rollback phần trừ kho
            return $this->reservations->findByRef($ref);
        }
    }

    /**
     * Trả linh kiện về kho; idempotent (đã trả rồi thì trả lại kết quả cũ).
     *
     * @param string $ref
     * @return Reservation
     * @throws BusinessException
     */
    public function release(string $ref): Reservation
    {
        return DB::transaction(function () use ($ref) {
            $reservation = $this->reservations->findByRefForUpdate($ref);
            if (!$reservation) {
                throw new BusinessException('Không tìm thấy lượt giữ linh kiện.', 'reservation_not_found', 404);
            }
            if ($reservation->status === ReservationStatus::RELEASED->value) {
                return $reservation;
            }
            if ($reservation->status === ReservationStatus::CONSUMED->value) {
                throw new BusinessException('Linh kiện đã sử dụng, không thể trả lại.', 'already_consumed', 409);
            }

            $this->parts->increment($reservation->part_id, $reservation->qty);
            $reservation->update(['status' => ReservationStatus::RELEASED->value]);

            return $reservation;
        });
    }

    /**
     * Tra cứu lượt giữ linh kiện (dùng khi gọi reserve bị timeout, chưa rõ kết quả).
     *
     * @param string $ref
     * @return Reservation
     * @throws BusinessException
     */
    public function find(string $ref): Reservation
    {
        $reservation = $this->reservations->findByRef($ref);
        if (!$reservation) {
            throw new BusinessException('Không tìm thấy lượt giữ linh kiện.', 'reservation_not_found', 404);
        }

        return $reservation;
    }
}
