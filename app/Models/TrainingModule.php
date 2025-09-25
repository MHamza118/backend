<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TrainingModule extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'title',
        'description',
        'qr_code',
        'video_url',
        'content',
        'duration',
        'category',
        'active',
        'order_index',
        'created_by',
    ];

    protected $casts = [
        'active' => 'boolean',
        'order_index' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = Str::uuid();
            }
            
            // Generate QR code if not provided
            if (!$model->qr_code) {
                $model->qr_code = static::generateUniqueQrCode();
            }
            
            // Set order index if not provided
            if (!$model->order_index) {
                $model->order_index = static::getNextOrderIndex();
            }
        });
    }

    /**
     * Generate a unique QR code
     */
    public static function generateUniqueQrCode($prefix = 'TRAIN_MODULE_')
    {
        do {
            $timestamp = substr(time(), -6);
            $random = strtoupper(substr(uniqid(), -3));
            $qrCode = $prefix . $timestamp . '_' . $random;
        } while (static::where('qr_code', $qrCode)->exists());

        return $qrCode;
    }

    /**
     * Get the next order index
     */
    public static function getNextOrderIndex()
    {
        return static::max('order_index') + 1;
    }

    /**
     * Relationship: Training module belongs to admin (creator)
     */
    public function createdBy()
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /**
     * Relationship: Training module has many assignments
     */
    public function assignments()
    {
        return $this->hasMany(TrainingAssignment::class, 'module_id');
    }

    /**
     * Relationship: Training module has many progress records
     */
    public function progress()
    {
        return $this->hasMany(TrainingProgress::class, 'module_id');
    }


    /**
     * Scope: Only active modules
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope: Order by order_index
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order_index')->orderBy('created_at');
    }

    /**
     * Scope: Filter by category
     */
    public function scopeByCategory($query, $category)
    {
        if ($category && $category !== 'all') {
            return $query->where('category', $category);
        }
        return $query;
    }

    /**
     * Get assignment statistics for this module
     */
    public function getAssignmentStats()
    {
        $assignments = $this->assignments();
        
        return [
            'total_assigned' => $assignments->count(),
            'completed' => $assignments->where('status', 'completed')->count(),
            'in_progress' => $assignments->where('status', 'in_progress')->count(),
            'unlocked' => $assignments->where('status', 'unlocked')->count(),
            'overdue' => $assignments->where('status', 'overdue')->count(),
        ];
    }

    /**
     * Get completion rate for this module
     */
    public function getCompletionRate()
    {
        $totalAssigned = $this->assignments()->count();
        if ($totalAssigned === 0) {
            return 0;
        }
        
        $completed = $this->assignments()->where('status', 'completed')->count();
        return round(($completed / $totalAssigned) * 100, 1);
    }

    /**
     * Get all available categories
     */
    public static function getCategories()
    {
        return static::distinct('category')
                    ->whereNotNull('category')
                    ->pluck('category')
                    ->sort()
                    ->values();
    }

    /**
     * Check if module can be deleted
     */
    public function canBeDeleted()
    {
        // Don't allow deletion if there are active assignments
        return !$this->assignments()->whereIn('status', ['assigned', 'unlocked', 'in_progress'])->exists();
    }

    /**
     * Soft delete by marking as inactive instead of actual deletion
     */
    public function softDelete()
    {
        return $this->update(['active' => false]);
    }
}