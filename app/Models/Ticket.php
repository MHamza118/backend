<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'title',
        'description',
        'category',
        'priority',
        'status',
        'location',
        'archived',
        'archived_at'
    ];

    protected $casts = [
        'archived' => 'boolean',
        'archived_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Available ticket categories
     */
    public const CATEGORIES = [
        'broken-equipment' => 'Broken Equipment',
        'software-issue' => 'Software Issue',
        'pos-problem' => 'POS Problem',
        'kitchen-equipment' => 'Kitchen Equipment',
        'facility-issue' => 'Facility Issue',
        'other' => 'Other'
    ];

    /**
     * Available priority levels
     */
    public const PRIORITIES = [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'urgent' => 'Urgent'
    ];

    /**
     * Available statuses
     */
    public const STATUSES = [
        'open' => 'Open',
        'in-progress' => 'In Progress',
        'resolved' => 'Resolved',
        'closed' => 'Closed'
    ];

    /**
     * Get the employee that owns the ticket
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the ticket responses
     */
    public function responses(): HasMany
    {
        return $this->hasMany(TicketResponse::class)->orderBy('created_at', 'asc');
    }

    /**
     * Get the public responses (non-internal)
     */
    public function publicResponses(): HasMany
    {
        return $this->hasMany(TicketResponse::class)->where('internal', false)->orderBy('created_at', 'asc');
    }

    /**
     * Scope for active (non-archived) tickets
     */
    public function scopeActive($query)
    {
        return $query->where('archived', false);
    }

    /**
     * Scope for archived tickets
     */
    public function scopeArchived($query)
    {
        return $query->where('archived', true);
    }

    /**
     * Scope for tickets by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for tickets by category
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope for tickets by priority
     */
    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope for tickets by employee
     */
    public function scopeByEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope for searching tickets
     */
    public function scopeSearch($query, $searchTerm)
    {
        return $query->where(function ($q) use ($searchTerm) {
            $q->where('title', 'LIKE', "%{$searchTerm}%")
              ->orWhere('description', 'LIKE', "%{$searchTerm}%")
              ->orWhereHas('employee', function ($empQuery) use ($searchTerm) {
                  $empQuery->where('first_name', 'LIKE', "%{$searchTerm}%")
                           ->orWhere('last_name', 'LIKE', "%{$searchTerm}%")
                           ->orWhere('email', 'LIKE', "%{$searchTerm}%");
              });
        });
    }

    /**
     * Archive the ticket
     */
    public function archive()
    {
        $this->update([
            'archived' => true,
            'archived_at' => now(),
            'status' => 'closed'
        ]);
    }

    /**
     * Update ticket status and set updated_at
     */
    public function updateStatus($status)
    {
        $this->update(['status' => $status]);
    }

    /**
     * Get priority order for sorting
     */
    public function getPriorityOrderAttribute()
    {
        $order = ['urgent' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
        return $order[$this->priority] ?? 2;
    }

    /**
     * Get category display name
     */
    public function getCategoryLabelAttribute()
    {
        return self::CATEGORIES[$this->category] ?? 'Other';
    }

    /**
     * Get priority display name
     */
    public function getPriorityLabelAttribute()
    {
        return self::PRIORITIES[$this->priority] ?? 'Medium';
    }

    /**
     * Get status display name
     */
    public function getStatusLabelAttribute()
    {
        return self::STATUSES[$this->status] ?? 'Open';
    }
}
