<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Location extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'address',
        'city',
        'state',
        'zip_code',
        'country',
        'phone',
        'email',
        'description',
        'active',
        'settings',
        'created_by',
    ];

    protected $casts = [
        'active' => 'boolean',
        'settings' => 'array',
    ];

    protected $dates = ['deleted_at'];

    /**
     * Get the admin who created this location
     */
    public function createdBy()
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /**
     * Get full address string
     */
    public function getFullAddressAttribute()
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->state,
            $this->zip_code,
            $this->country
        ]);
        
        return implode(', ', $parts);
    }

    /**
     * Scope for active locations
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope for inactive locations
     */
    public function scopeInactive($query)
    {
        return $query->where('active', false);
    }
}
