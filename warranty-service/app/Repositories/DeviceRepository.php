<?php

namespace App\Repositories;

use App\Models\Device;
use Illuminate\Database\Eloquent\Collection;

class DeviceRepository
{
    /**
     * @param string $serial
     * @return Device|null
     */
    public function findBySerial(string $serial): ?Device
    {
        return Device::query()->where('serial', $serial)->first();
    }

    /**
     * @return Collection
     */
    public function all(): Collection
    {
        return Device::query()->orderBy('serial')->get();
    }
}
