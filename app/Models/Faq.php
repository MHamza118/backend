<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Faq extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'question',
        'answer',
        'category',
        'order',
        'active',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'active' => 'boolean',
        'order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    protected $dates = ['deleted_at'];

    // Category constants
    const CATEGORY_PAYROLL = 'payroll';
    const CATEGORY_SCHEDULING = 'scheduling';
    const CATEGORY_UNIFORM = 'uniform';
    const CATEGORY_TRAINING = 'training';
    const CATEGORY_POLICIES = 'policies';
    const CATEGORY_BENEFITS = 'benefits';
    const CATEGORY_OTHER = 'other';

    /**
     * Get available categories
     */
    public static function getCategories()
    {
        return [
            self::CATEGORY_PAYROLL => 'Pay & Tips',
            self::CATEGORY_SCHEDULING => 'Scheduling',
            self::CATEGORY_UNIFORM => 'Uniform & Dress Code',
            self::CATEGORY_TRAINING => 'Training',
            self::CATEGORY_POLICIES => 'Policies',
            self::CATEGORY_BENEFITS => 'Benefits',
            self::CATEGORY_OTHER => 'Other'
        ];
    }

    /**
     * Get the admin who created this FAQ
     */
    public function creator()
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /**
     * Get the admin who last updated this FAQ
     */
    public function updater()
    {
        return $this->belongsTo(Admin::class, 'updated_by');
    }

    /**
     * Scope for active FAQs
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope by category
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope for ordering by display order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order', 'asc')->orderBy('created_at', 'asc');
    }

    /**
     * Get category name
     */
    public function getCategoryNameAttribute()
    {
        $categories = self::getCategories();
        return $categories[$this->category] ?? $this->category;
    }

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // Set default order when creating new FAQ
        static::creating(function ($faq) {
            if (is_null($faq->order)) {
                $maxOrder = static::max('order') ?? 0;
                $faq->order = $maxOrder + 1;
            }
        });
    }
}
