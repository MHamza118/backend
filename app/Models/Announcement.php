<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Announcement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'message',
        'type',
        'priority',
        'start_date',
        'end_date',
        'is_active',
        'is_dismissible',
        'action_text',
        'action_url',
        'target_audience',
        'target_criteria',
        'created_by'
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_active' => 'boolean',
        'is_dismissible' => 'boolean',
        'target_criteria' => 'array'
    ];

    protected $dates = ['deleted_at'];

    /**
     * Get customers who dismissed this announcement
     */
    public function dismissedByCustomers()
    {
        return $this->belongsToMany(Customer::class, 'customer_dismissed_announcements')
                    ->withTimestamps();
    }

    /**
     * Scope for active announcements
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where('start_date', '<=', now())
                    ->where('end_date', '>=', now());
    }

    /**
     * Scope by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope by priority
     */
    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope for customer-specific announcements
     */
    public function scopeForCustomer($query, Customer $customer)
    {
        return $query->where(function ($q) use ($customer) {
            $q->where('target_audience', 'all')
              ->orWhere(function ($subQuery) use ($customer) {
                  $subQuery->where('target_audience', 'loyalty_tier')
                           ->where('target_criteria', 'like', '%' . $customer->loyalty_tier . '%');
              })
              ->orWhere(function ($subQuery) use ($customer) {
                  $subQuery->where('target_audience', 'location')
                           ->where('target_criteria', 'like', '%' . $customer->home_location . '%');
              });
        });
    }
}
