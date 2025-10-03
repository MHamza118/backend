<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TrainingProgress extends Model
{
    use HasFactory;

    protected $table = 'training_progress';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'assignment_id',
        'employee_id',
        'module_id',
        'session_start',
        'session_end',
        'time_spent_minutes',
        'progress_data',
    ];

    protected $casts = [
        'session_start' => 'datetime',
        'session_end' => 'datetime',
        'time_spent_minutes' => 'integer',
        'progress_data' => 'array',
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
            if (!$model->session_start) {
                $model->session_start = now();
            }
        });
    }

    /**
     * Relationship: Progress belongs to assignment
     */
    public function assignment()
    {
        return $this->belongsTo(TrainingAssignment::class, 'assignment_id');
    }

    /**
     * Relationship: Progress belongs to employee
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Relationship: Progress belongs to module
     */
    public function module()
    {
        return $this->belongsTo(TrainingModule::class, 'module_id');
    }

    /**
     * Scope: For specific assignment
     */
    public function scopeForAssignment($query, $assignmentId)
    {
        return $query->where('assignment_id', $assignmentId);
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
     * Scope: Active sessions (not ended)
     */
    public function scopeActive($query)
    {
        return $query->whereNull('session_end');
    }

    /**
     * Scope: Completed sessions
     */
    public function scopeCompleted($query)
    {
        return $query->whereNotNull('session_end');
    }

    /**
     * End the current session
     */
    public function endSession($timeSpent = null)
    {
        $sessionEnd = now();
        
        // Calculate time spent if not provided
        if ($timeSpent === null) {
            $timeSpent = $this->session_start->diffInMinutes($sessionEnd);
        }
        
        return $this->update([
            'session_end' => $sessionEnd,
            'time_spent_minutes' => $timeSpent,
        ]);
    }

    /**
     * Update progress data
     */
    public function updateProgress($progressData)
    {
        return $this->update([
            'progress_data' => array_merge($this->progress_data ?: [], $progressData),
        ]);
    }

    /**
     * Check if session is active
     */
    public function isActive()
    {
        return !$this->session_end;
    }

    /**
     * Get session duration in minutes
     */
    public function getSessionDuration()
    {
        if ($this->session_end) {
            return $this->time_spent_minutes;
        }
        
        return $this->session_start->diffInMinutes(now());
    }

    /**
     * Static method to create a new progress session
     */
    public static function startSession($assignmentId, $employeeId, $moduleId, $progressData = [])
    {
        return static::create([
            'assignment_id' => $assignmentId,
            'employee_id' => $employeeId,
            'module_id' => $moduleId,
            'session_start' => now(),
            'progress_data' => $progressData,
        ]);
    }

    /**
     * Get average time spent across all progress records for a module
     */
    public static function getAverageTimeForModule($moduleId)
    {
        return static::forModule($moduleId)
                   ->completed()
                   ->avg('time_spent_minutes') ?: 0;
    }

    /**
     * Get total time spent by an employee on a module
     */
    public static function getTotalTimeForEmployeeModule($employeeId, $moduleId)
    {
        return static::forEmployee($employeeId)
                   ->forModule($moduleId)
                   ->sum('time_spent_minutes');
    }
}