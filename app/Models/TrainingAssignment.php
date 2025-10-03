<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Carbon\Carbon;

class TrainingAssignment extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'module_id',
        'employee_id',
        'assigned_by',
        'assigned_at',
        'due_date',
        'status',
        'unlocked_at',
        'started_at',
        'completed_at',
        'completion_data',
        'reset_count',
        'last_reset_at',
        'notes',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'due_date' => 'datetime',
        'unlocked_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_reset_at' => 'datetime',
        'completion_data' => 'array',
        'reset_count' => 'integer',
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
            if (!$model->assigned_at) {
                $model->assigned_at = now();
            }
        });

        static::updating(function ($model) {
            // Auto-update status based on due date
            if ($model->due_date && !$model->completed_at && $model->due_date < now()) {
                $model->status = 'overdue';
            }
        });
    }

    /**
     * Relationship: Assignment belongs to training module
     */
    public function module()
    {
        return $this->belongsTo(TrainingModule::class, 'module_id');
    }

    /**
     * Relationship: Assignment belongs to employee
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Relationship: Assignment belongs to admin (assigner)
     */
    public function assigner()
    {
        return $this->belongsTo(Admin::class, 'assigned_by');
    }

    /**
     * Relationship: Assignment has many progress records
     */
    public function progress()
    {
        return $this->hasMany(TrainingProgress::class, 'assignment_id');
    }


    /**
     * Scope: Filter by status
     */
    public function scopeByStatus($query, $status)
    {
        if ($status && $status !== 'all') {
            return $query->where('status', $status);
        }
        return $query;
    }

    /**
     * Scope: Overdue assignments
     */
    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
                    ->whereNotIn('status', ['completed'])
                    ->where('status', '!=', 'overdue');
    }

    /**
     * Scope: Due soon (within specified days)
     */
    public function scopeDueSoon($query, $days = 3)
    {
        $dueDate = Carbon::now()->addDays($days);
        return $query->where('due_date', '<=', $dueDate)
                    ->where('due_date', '>', now())
                    ->whereNotIn('status', ['completed']);
    }

    /**
     * Scope: Assigned by specific admin
     */
    public function scopeAssignedBy($query, $adminId)
    {
        return $query->where('assigned_by', $adminId);
    }

    /**
     * Scope: For specific employee
     */
    public function scopeForEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope: For specific module
     */
    public function scopeForModule($query, $moduleId)
    {
        return $query->where('module_id', $moduleId);
    }

    /**
     * Check if assignment is overdue
     */
    public function isOverdue()
    {
        return $this->due_date && 
               $this->due_date < now() && 
               !$this->completed_at;
    }

    /**
     * Check if assignment is due soon
     */
    public function isDueSoon($days = 3)
    {
        if (!$this->due_date || $this->completed_at) {
            return false;
        }
        
        $dueSoonDate = Carbon::now()->addDays($days);
        return $this->due_date <= $dueSoonDate && $this->due_date > now();
    }

    /**
     * Get days until due (negative if overdue)
     */
    public function getDaysUntilDue()
    {
        if (!$this->due_date) {
            return null;
        }
        
        return Carbon::now()->diffInDays($this->due_date, false);
    }

    /**
     * Mark assignment as unlocked
     */
    public function unlock()
    {
        return $this->update([
            'status' => 'unlocked',
            'unlocked_at' => now(),
        ]);
    }

    /**
     * Mark assignment as started
     */
    public function start()
    {
        return $this->update([
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }

    /**
     * Mark assignment as completed
     */
    public function complete($completionData = [])
    {
        return $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'completion_data' => $completionData,
        ]);
    }

    /**
     * Reset assignment progress
     */
    public function reset($reason = null)
    {
        return $this->update([
            'status' => 'assigned',
            'unlocked_at' => null,
            'started_at' => null,
            'completed_at' => null,
            'completion_data' => null,
            'reset_count' => $this->reset_count + 1,
            'last_reset_at' => now(),
            'notes' => $reason ? ($this->notes ? $this->notes . "\n" . $reason : $reason) : $this->notes,
        ]);
    }

    /**
     * Get total time spent on this assignment
     */
    public function getTotalTimeSpent()
    {
        $completionData = $this->completion_data ?: [];
        return isset($completionData['time_spent_minutes']) ? $completionData['time_spent_minutes'] : 0;
    }

    /**
     * Update status if overdue
     */
    public function updateOverdueStatus()
    {
        if ($this->isOverdue() && $this->status !== 'overdue') {
            $this->update(['status' => 'overdue']);
        }
    }

    /**
     * Static method to update all overdue assignments
     */
    public static function updateOverdueAssignments()
    {
        return static::overdue()->update(['status' => 'overdue']);
    }
}