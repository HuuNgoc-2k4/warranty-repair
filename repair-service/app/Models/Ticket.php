<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    protected $fillable = [
        'serial', 'customer', 'phone', 'issue', 'in_warranty', 'status', 'part_id', 'qty',
        'idempotency_key', 'request_hash', 'failure_reason'
    ];

    protected $hidden = ['request_hash'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'in_warranty' => 'boolean',
            'part_id' => 'integer',
            'qty' => 'integer'
        ];
    }

    /**
     * @return HasMany
     */
    public function events(): HasMany
    {
        return $this->hasMany(TicketEvent::class)->orderBy('id');
    }
}
