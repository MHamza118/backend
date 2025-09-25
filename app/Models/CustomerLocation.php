<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'name',
        'address',
        'city',
        'state',
        'zip_code',
        'is_home',
        'is_active'
    ];

    protected $casts = [
        'is_home' => 'boolean',
        'is_active' => 'boolean'
    ];

    /**
     * Get the customer that owns this location
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Scope for home locations
     */
    public function scopeHome($query)
    {
        return $query->where('is_home', true);
    }

    /**
     * Scope for active locations
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
