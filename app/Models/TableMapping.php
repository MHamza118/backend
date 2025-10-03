<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TableMapping extends Model
{
    use HasFactory;

    protected $table = 'table_mappings';

    protected $fillable = [
        'order_number',
        'submission_id',
        'submitted_at',
        'table_number', 
        'area',
        'status',
        'source',
        'delivered_by',
        'delivered_at',
        'cleared_at',
        'clear_reason',
        'notes',
        'update_count'
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cleared_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CLEARED = 'cleared';

    // Source constants
    const SOURCE_CUSTOMER = 'customer';
    const SOURCE_ADMIN = 'admin';

    // Area constants
    const AREA_DINING = 'dining';
    const AREA_PATIO = 'patio';
    const AREA_BAR = 'bar';

    public static function getValidStatuses()
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_DELIVERED,
            self::STATUS_CLEARED
        ];
    }

    public static function getValidSources()
    {
        return [
            self::SOURCE_CUSTOMER,
            self::SOURCE_ADMIN
        ];
    }

    public static function getValidAreas()
    {
        return [
            self::AREA_DINING,
            self::AREA_PATIO,
            self::AREA_BAR
        ];
    }

    public static function getValidTableNumbers()
    {
        // With flexible numbering, there is no fixed list anymore.
        // Kept for backward compatibility; return an empty array to indicate "no fixed set".
        return [];
    }

    public static function getAreaForTable($tableNumber)
    {
        $tableNumber = strtoupper(trim($tableNumber));
        
        // Numeric-only tables map to DINING by default (supports any number of digits)
        if (preg_match('/^[0-9]+$/', $tableNumber)) {
            return self::AREA_DINING;
        }
        
        // Prefix-based areas remain supported for backward compatibility
        if (preg_match('/^P[0-9]+$/', $tableNumber)) {
            return self::AREA_PATIO;
        }
        
        if (preg_match('/^B[0-9]+$/', $tableNumber)) {
            return self::AREA_BAR;
        }
        
        return 'unknown';
    }

    public static function isValidTableNumber($tableNumber)
    {
        $tableNumber = trim((string) $tableNumber);
        // Accept numeric tables (1+ digits), P+numeric, or B+numeric
        return $tableNumber !== '' && preg_match('/^([0-9]+|P[0-9]+|B[0-9]+)$/i', $tableNumber);
    }

    public static function isValidOrderNumber($orderNumber)
    {
        $orderNumber = trim($orderNumber);
        return !empty($orderNumber) && preg_match('/^[0-9]+$/', $orderNumber);
    }

    // Relationships
    public function order()
    {
        return $this->belongsTo(TableOrder::class, 'order_number', 'order_number');
    }

    public function specificOrder()
    {
        return $this->hasOne(TableOrder::class, 'mapping_id', 'id');
    }

    public function notifications()
    {
        return $this->hasMany(TableNotification::class, 'order_number', 'order_number');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeByArea($query, $area)
    {
        return $query->where('area', $area);
    }

    public function scopeByTable($query, $tableNumber)
    {
        return $query->where('table_number', strtoupper($tableNumber));
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    // Accessors & Mutators
    public function setTableNumberAttribute($value)
    {
        $this->attributes['table_number'] = strtoupper($value);
    }

    public function getIsActiveAttribute()
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function getDeliveryTimeMinutesAttribute()
    {
        if (!$this->delivered_at) {
            return null;
        }

        $start = $this->created_at;
        $end = $this->delivered_at;
        
        return $start->diffInMinutes($end);
    }
}
