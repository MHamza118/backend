<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Questionnaire extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'questions',
        'steps',
        'settings',
        'is_active',
        'order_index',
        'created_by'
    ];

    protected $casts = [
        'questions' => 'array',
        'steps' => 'array',
        'settings' => 'array',
        'is_active' => 'boolean'
    ];

    protected $dates = ['deleted_at'];

    /**
     * Get the user who created this questionnaire
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope for active questionnaires
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope ordered by order_index
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order_index');
    }
}
