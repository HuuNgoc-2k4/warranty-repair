<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketEvent extends Model
{
    protected $fillable = ['ticket_id', 'from_status', 'to_status', 'note'];

    /**
     * @return BelongsTo
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
