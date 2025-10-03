<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'position' => $this->position,
            'department' => $this->department,
            'stage' => $this->stage,
            'status' => $this->status,
            'location' => $this->location,
            'can_access_dashboard' => $this->canAccessDashboard(),
            'next_stage' => $this->getNextStage(),
            'questionnaire_responses' => $this->questionnaire_responses,
            'profile_data' => $this->profile_data,
            'personal_info' => $this->when(isset($this->personal_info), $this->personal_info) ?: ($this->profile_data['personal_info'] ?? null),
            'questionnaire_files' => $this->when($this->fileUploads, function () {
                return $this->fileUploads->map(function ($file) {
                    return [
                        'id' => $file->id,
                        'original_filename' => $file->original_filename,
                        'file_path' => $file->file_path,
                        'mime_type' => $file->mime_type,
                        'file_size' => $file->file_size,
                        'question_index' => $file->question_index,
                        'uploaded_at' => $file->created_at->toISOString(),
                    ];
                });
            }),
            'onboarding_progress' => $this->getOnboardingProgress(),
            'onboarding_pages_progress' => $this->getOnboardingPageProgress(),
            'is_personal_info_complete' => $this->isPersonalInfoComplete(),
            'approved_at' => $this->approved_at?->toISOString(),
            'approved_by' => $this->when($this->approvedBy, function () {
                return [
                    'id' => $this->approvedBy->id,
                    'name' => $this->approvedBy->name ?? $this->approvedBy->full_name,
                    'email' => $this->approvedBy->email,
                ];
            }),
            'rejection_reason' => $this->when($this->status === 'rejected', $this->rejection_reason),
            // Training-related data
            'training_assignments' => $this->when($this->trainingAssignments, function () {
                return $this->trainingAssignments->map(function ($assignment) {
                    return [
                        'id' => $assignment->id,
                        'module_id' => $assignment->module_id,
                        'status' => $assignment->status,
                        'assigned_at' => $assignment->assigned_at?->toISOString(),
                        'due_date' => $assignment->due_date?->toISOString(),
                        'unlocked_at' => $assignment->unlocked_at?->toISOString(),
                        'started_at' => $assignment->started_at?->toISOString(),
                        'completed_at' => $assignment->completed_at?->toISOString(),
                        'completion_data' => $assignment->completion_data,
                        'reset_count' => $assignment->reset_count,
                        'last_reset_at' => $assignment->last_reset_at?->toISOString(),
                        'notes' => $assignment->notes,
                        'module' => $assignment->module ? [
                            'id' => $assignment->module->id,
                            'title' => $assignment->module->title,
                            'description' => $assignment->module->description,
                            'category' => $assignment->module->category,
                            'duration' => $assignment->module->duration,
                            'qr_code' => $assignment->module->qr_code,
                        ] : null,
                    ];
                });
            }),
            'training_progress' => $this->when($this->trainingProgress, function () {
                return $this->trainingProgress->map(function ($progress) {
                    return [
                        'id' => $progress->id,
                        'assignment_id' => $progress->assignment_id,
                        'module_id' => $progress->module_id,
                        'session_start' => $progress->session_start?->toISOString(),
                        'session_end' => $progress->session_end?->toISOString(),
                        'time_spent_minutes' => $progress->time_spent_minutes,
                        'progress_data' => $progress->progress_data,
                        'is_active' => $progress->isActive(),
                        'session_duration' => $progress->getSessionDuration(),
                    ];
                });
            }),
            'completed_training' => $this->when($this->trainingAssignments, function () {
                return $this->trainingAssignments->where('status', 'completed')->map(function ($assignment) {
                    return [
                        'id' => $assignment->id,
                        'module_id' => $assignment->module_id,
                        'completed_at' => $assignment->completed_at?->toISOString(),
                        'completion_data' => $assignment->completion_data,
                        'module_title' => $assignment->module?->title,
                    ];
                });
            }),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
