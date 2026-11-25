<?php

namespace App\Console\Commands;

use App\Services\TicketService;
use Illuminate\Console\Command;

class ReconcileTickets extends Command
{
    protected $signature = 'tickets:reconcile {--older-than= : Chỉ đối soát phiếu PENDING quá số giây này}';

    protected $description = 'Đối soát phiếu PENDING quá hạn bằng cách tra lại lượt giữ linh kiện ở Inventory';

    /**
     * @param TicketService $ticketService
     * @return int
     */
    public function handle(TicketService $ticketService): int
    {
        $olderThan = (int) ($this->option('older-than') ?? TicketService::RECONCILE_AFTER_SECONDS);
        $summary = $ticketService->reconcile($olderThan);

        $this->info(sprintf(
            'Đã kiểm tra %d phiếu: %d tiếp nhận, %d thất bại, %d vẫn chờ.',
            $summary['checked'],
            $summary['received'],
            $summary['failed'],
            $summary['pending']
        ));

        return self::SUCCESS;
    }
}
