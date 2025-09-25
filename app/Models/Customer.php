<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'password',
        'phone',
        'home_location',
        'loyalty_points',
        'total_orders',
        'total_spent',
        'preferences',
        'status',
        'last_visit'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_visit' => 'datetime',
        'preferences' => 'array',
        'loyalty_points' => 'integer',
        'total_orders' => 'integer',
        'total_spent' => 'decimal:2'
    ];

    protected $dates = ['deleted_at'];

    /**
     * Get customer's locations
     */
    public function locations()
    {
        return $this->hasMany(CustomerLocation::class);
    }

    /**
     * Get customer's orders
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get customer's favorite items
     */
    public function favoriteItems()
    {
        return $this->hasMany(CustomerFavoriteItem::class);
    }

    /**
     * Get customer's announcements (dismissed)
     */
    public function dismissedAnnouncements()
    {
        return $this->belongsToMany(Announcement::class, 'customer_dismissed_announcements')
                    ->withTimestamps();
    }

    /**
     * Get customer's messages
     */
    public function messages()
    {
        return $this->hasMany(CustomerMessage::class);
    }

    /**
     * Get customer's rewards
     */
    public function rewards()
    {
        return $this->hasMany(CustomerReward::class);
    }

    /**
     * Determine loyalty tier based on points
     */
    public function getLoyaltyTierAttribute()
    {
        if ($this->loyalty_points >= 2500) return 'Platinum';
        if ($this->loyalty_points >= 1000) return 'Gold';
        if ($this->loyalty_points >= 500) return 'Silver';
        return 'Bronze';
    }

    /**
     * Get home location relationship
     */
    public function homeLocationRecord()
    {
        return $this->locations()->where('is_home', true)->first();
    }

    /**
     * Scope for active customers
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVE');
    }

    /**
     * Scope by loyalty tier
     */
    public function scopeByLoyaltyTier($query, $tier)
    {
        switch (strtolower($tier)) {
            case 'platinum':
                return $query->where('loyalty_points', '>=', 2500);
            case 'gold':
                return $query->where('loyalty_points', '>=', 1000)->where('loyalty_points', '<', 2500);
            case 'silver':
                return $query->where('loyalty_points', '>=', 500)->where('loyalty_points', '<', 1000);
            case 'bronze':
                return $query->where('loyalty_points', '<', 500);
            default:
                return $query;
        }
    }
}
