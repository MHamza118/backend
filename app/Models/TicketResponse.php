<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'message',
        'responded_by',
        'internal'
    ];

    protected $casts = [
        'internal' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the ticket that owns the response
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Scope for public responses (non-internal)
     */
    public function scopePublic($query)
    {
        return $query->where('internal', false);
    }

    /**
     * Scope for internal responses
     */
    public function scopeInternal($query)
    {
        return $query->where('internal', true);
    }
}
