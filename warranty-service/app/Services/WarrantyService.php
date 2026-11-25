<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Device;
use App\Repositories\DeviceRepository;

class WarrantyService
{
    /**
     * @param DeviceRepository $devices
     */
    public function __construct(protected DeviceRepository $devices)
    {
    }

    /**
     * Danh sách thiết bị kèm tình trạng bảo hành hiện tại.
     *
     * @return array
     */
    public function listDevices(): array
    {
        return $this->devices->all()->map(fn (Device $device) => $this->describe($device))->all();
    }

    /**
     * Tra cứu bảo hành theo serial.
     *
     * @param string $serial
     * @return array
     * @throws BusinessException
     */
    public function check(string $serial): array
    {
        $device = $this->devices->findBySerial($serial);
        if (!$device) {
            throw new BusinessException('Không tìm thấy thiết bị theo serial.', 'device_not_found', 404);
        }

        return $this->describe($device);
    }

    /**
     * Tính ngày hết hạn; còn hạn khi hôm nay chưa vượt quá ngày hết hạn.
     *
     * @param Device $device
     * @return array
     */
    protected function describe(Device $device): array
    {
        $expiresAt = $device->purchase_date->copy()->addMonthsNoOverflow($device->warranty_months);

        return [
            'serial' => $device->serial,
            'model' => $device->model,
            'purchase_date' => $device->purchase_date->toDateString(),
            'warranty_months' => $device->warranty_months,
            'expires_at' => $expiresAt->toDateString(),
            'valid' => !now()->startOfDay()->greaterThan($expiresAt)
        ];
    }
}
