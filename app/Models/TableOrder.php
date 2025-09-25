<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TableOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'unique_identifier',
        'mapping_id',
        'table_number',
        'area',
        'customer_name',
        'status',
        'notes'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_PREPARING = 'preparing';
    const STATUS_READY = 'ready';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_COMPLETED = 'completed';

    public static function getValidStatuses()
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_PREPARING,
            self::STATUS_READY,
            self::STATUS_DELIVERED,
            self::STATUS_COMPLETED
        ];
    }

    // Relationships
    public function mapping()
    {
        return $this->belongsTo(TableMapping::class, 'mapping_id', 'id');
    }

    public function mappings()
    {
        return $this->hasMany(TableMapping::class, 'order_number', 'order_number');
    }

    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    // Accessors
    public function getIsActiveAttribute()
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PREPARING, self::STATUS_READY]);
    }

    public function getIsCompletedAttribute()
    {
        return in_array($this->status, [self::STATUS_DELIVERED, self::STATUS_COMPLETED]);
    }
}
