<?php

namespace App\Repositories;

use App\Models\Reservation;

class ReservationRepository
{
    /**
     * @param string $ref
     * @return Reservation|null
     */
    public function findByRef(string $ref): ?Reservation
    {
        return Reservation::query()->where('ref', $ref)->first();
    }

    /**
     * Khóa dòng để hai lệnh release song song không cộng kho hai lần.
     *
     * @param string $ref
     * @return Reservation|null
     */
    public function findByRefForUpdate(string $ref): ?Reservation
    {
        return Reservation::query()->where('ref', $ref)->lockForUpdate()->first();
    }

    /**
     * @param array $data
     * @return Reservation
     */
    public function create(array $data): Reservation
    {
        return Reservation::query()->create($data);
    }
}
