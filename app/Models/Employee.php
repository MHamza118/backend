<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name', 
        'email',
        'password',
        'phone',
        'position',
        'department',
        'stage',
        'status',
        'location',
        'questionnaire_responses',
        'profile_data',
        'approved_at',
        'approved_by',
        'rejection_reason'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'approved_at' => 'datetime',
        'questionnaire_responses' => 'array',
        'profile_data' => 'array',
        'password' => 'hashed',
    ];

    protected $dates = ['deleted_at'];

    // Stage constants
    const STAGE_INTERVIEW = 'interview';
    const STAGE_LOCATION_SELECTED = 'location_selected';
    const STAGE_QUESTIONNAIRE_COMPLETED = 'questionnaire_completed';
    const STAGE_ACTIVE = 'active';

    // Status constants
    const STATUS_PENDING_APPROVAL = 'pending_approval';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_PAUSED = 'paused';
    const STATUS_INACTIVE = 'inactive';

    /**
     * Get the user who approved this employee
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get file uploads for this employee
     */
    public function fileUploads()
    {
        return $this->hasMany(EmployeeFileUpload::class);
    }

    /**
     * Get onboarding progress records for this employee
     */
    public function onboardingProgress()
    {
        return $this->hasMany(EmployeeOnboardingProgress::class);
    }

    /**
     * Get completed onboarding pages for this employee
     */
    public function completedOnboardingPages()
    {
        return $this->belongsToMany(
            OnboardingPage::class,
            'employee_onboarding_progress',
            'employee_id',
            'onboarding_page_id'
        )->wherePivot('status', 'completed');
    }

    /**
     * Get training assignments for this employee
     */
    public function trainingAssignments()
    {
        return $this->hasMany(TrainingAssignment::class, 'employee_id');
    }

    /**
     * Get training progress records for this employee
     */
    public function trainingProgress()
    {
        return $this->hasMany(TrainingProgress::class, 'employee_id');
    }

    /**
     * Get tickets submitted by this employee
     */
    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'employee_id');
    }

    /**
     * Get full name attribute
     */
    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    /**
     * Check if employee is approved
     */
    public function isApproved()
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if employee is pending approval
     */
    public function isPendingApproval()
    {
        return $this->status === self::STATUS_PENDING_APPROVAL;
    }

    /**
     * Check if employee can access dashboard
     */
    public function canAccessDashboard()
    {
        return $this->status === self::STATUS_APPROVED && $this->stage === self::STAGE_ACTIVE;
    }

    /**
     * Check if employee is paused
     */
    public function isPaused()
    {
        return $this->status === self::STATUS_PAUSED;
    }

    /**
     * Check if employee is inactive
     */
    public function isInactive()
    {
        return $this->status === self::STATUS_INACTIVE;
    }

    /**
     * Check if employee is active (approved and not paused/inactive)
     */
    public function isActive()
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Get next stage in onboarding flow
     */
    public function getNextStage()
    {
        $stageOrder = [
            self::STAGE_INTERVIEW,
            self::STAGE_LOCATION_SELECTED,
            self::STAGE_QUESTIONNAIRE_COMPLETED,
            self::STAGE_ACTIVE
        ];
        
        $currentIndex = array_search($this->stage, $stageOrder);
        return $currentIndex !== false && $currentIndex < count($stageOrder) - 1 
            ? $stageOrder[$currentIndex + 1] 
            : null;
    }

    /**
     * Move to next stage
     */
    public function moveToNextStage()
    {
        $nextStage = $this->getNextStage();
        if ($nextStage && $this->status === self::STATUS_PENDING_APPROVAL) {
            $this->stage = $nextStage;
            $this->save();
        }
    }

    /**
     * Scope for pending approval employees
     */
    public function scopePendingApproval($query)
    {
        return $query->where('status', self::STATUS_PENDING_APPROVAL);
    }

    /**
     * Scope for approved employees
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope by stage
     */
    public function scopeByStage($query, $stage)
    {
        return $query->where('stage', $stage);
    }

    /**
     * Get onboarding progress information
     */
    public function getOnboardingProgress()
    {
        $stages = [
            self::STAGE_INTERVIEW => 'Interview Scheduled',
            self::STAGE_LOCATION_SELECTED => 'Location Selected',
            self::STAGE_QUESTIONNAIRE_COMPLETED => 'Questionnaire Completed',
            self::STAGE_ACTIVE => 'Active Employee'
        ];

        $currentStageIndex = array_search($this->stage, array_keys($stages));
        $totalStages = count($stages);
        $progressPercentage = $currentStageIndex !== false ? (($currentStageIndex + 1) / $totalStages) * 100 : 0;

        return [
            'current_stage' => $this->stage,
            'current_stage_name' => $stages[$this->stage] ?? 'Unknown Stage',
            'completed_stages' => $currentStageIndex !== false ? $currentStageIndex + 1 : 0,
            'total_stages' => $totalStages,
            'progress_percentage' => round($progressPercentage, 1),
            'status' => $this->status,
            'can_proceed' => $this->status === self::STATUS_PENDING_APPROVAL,
            'is_completed' => $this->status === self::STATUS_APPROVED && $this->stage === self::STAGE_ACTIVE,
        ];
    }

    /**
     * Get onboarding page completion progress
     */
    public function getOnboardingPageProgress()
    {
        $totalPages = OnboardingPage::active()->count();
        $completedPages = $this->onboardingProgress()->completed()->count();
        
        return [
            'total' => $totalPages,
            'completed' => $completedPages,
            'remaining' => $totalPages - $completedPages,
            'percentage' => $totalPages > 0 ? round(($completedPages / $totalPages) * 100, 1) : 0,
            'is_complete' => $totalPages > 0 && $completedPages >= $totalPages
        ];
    }

    /**
     * Get the onboarding progress for a specific page
     */
    public function getOnboardingPageStatus($pageId)
    {
        $progress = $this->onboardingProgress()
            ->where('onboarding_page_id', $pageId)
            ->first();
        
        return $progress ? $progress->status : 'not_started';
    }

    /**
     * Complete an onboarding page
     */
    public function completeOnboardingPage($pageId, $signature = null)
    {
        $progress = $this->onboardingProgress()
            ->where('onboarding_page_id', $pageId)
            ->first();
        
        if (!$progress) {
            $progress = $this->onboardingProgress()->create([
                'onboarding_page_id' => $pageId,
                'status' => 'completed',
                'signature' => $signature,
                'completed_at' => now(),
            ]);
        } else {
            $progress->markAsCompleted($signature);
        }
        
        return $progress;
    }

    /**
     * Check if personal information is complete
     */
    public function isPersonalInfoComplete()
    {
        $requiredFields = ['first_name', 'last_name', 'email', 'phone'];
        
        foreach ($requiredFields as $field) {
            if (empty($this->{$field})) {
                return false;
            }
        }
        
        return true;
    }
}
