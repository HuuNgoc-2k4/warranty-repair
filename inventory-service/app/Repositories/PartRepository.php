<?php

namespace App\Repositories;

use App\Models\Part;
use Illuminate\Database\Eloquent\Collection;

class PartRepository
{
    /**
     * @return Collection
     */
    public function all(): Collection
    {
        return Part::query()->orderBy('id')->get();
    }

    /**
     * @param int $id
     * @return bool
     */
    public function exists(int $id): bool
    {
        return Part::query()->whereKey($id)->exists();
    }

    /**
     * Trừ kho nguyên tử: chỉ trừ khi còn đủ, trả về false nếu không đủ.
     *
     * @param int $id
     * @param int $qty
     * @return bool
     */
    public function decrementIfAvailable(int $id, int $qty): bool
    {
        return Part::query()->whereKey($id)->where('available', '>=', $qty)->decrement('available', $qty) > 0;
    }

    /**
     * @param int $id
     * @param int $qty
     * @return void
     */
    public function increment(int $id, int $qty): void
    {
        Part::query()->whereKey($id)->increment('available', $qty);
    }
}
