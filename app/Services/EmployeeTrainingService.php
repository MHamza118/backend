<?php

namespace App\Services;

use App\Models\TrainingModule;
use App\Models\TrainingAssignment;
use App\Models\TrainingProgress;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class EmployeeTrainingService
{
    /**
     * Get training modules assigned to employee
     */
    public function getAssignedTrainingModules(string $employeeId): array
    {
        $employee = Employee::find($employeeId);
        if (!$employee) {
            throw new \Exception('Employee not found');
        }

        $assignments = TrainingAssignment::where('employee_id', $employeeId)
            ->with(['module'])
            ->whereHas('module', function ($query) {
                $query->where('active', true);
            })
            ->whereNotIn('status', ['removed'])
            ->orderBy('assigned_at', 'desc')
            ->get();

        $formattedAssignments = $assignments->map(function ($assignment) {
            return [
                'id' => $assignment->id,
                'module' => [
                    'id' => $assignment->module->id,
                    'title' => $assignment->module->title,
                    'description' => $assignment->module->description,
                    'category' => $assignment->module->category,
                    'duration' => $assignment->module->duration,
                    'video_url' => $assignment->module->video_url,
                    'qr_code' => $assignment->module->qr_code
                ],
                'status' => $assignment->status,
                'assigned_at' => $assignment->assigned_at->toISOString(),
                'due_date' => $assignment->due_date?->toISOString(),
                'unlocked_at' => $assignment->unlocked_at?->toISOString(),
                'started_at' => $assignment->started_at?->toISOString(),
                'completed_at' => $assignment->completed_at?->toISOString(),
                'progress' => $this->calculateProgress($assignment),
                'is_overdue' => $this->isOverdue($assignment),
                'can_unlock' => $this->canUnlock($assignment),
                'notes' => $assignment->notes
            ];
        });

        $stats = $this->calculateEmployeeStats($assignments);

        // Also provide all available training modules for the employee
        $allModules = TrainingModule::where('active', true)
            ->ordered()
            ->get()
            ->map(function ($module) use ($employeeId) {
                // Check if this module is assigned to the employee
                $assignment = TrainingAssignment::where('employee_id', $employeeId)
                    ->where('module_id', $module->id)
                    ->whereNotIn('status', ['removed'])
                    ->first();
                
                return [
                    'id' => $module->id,
                    'title' => $module->title,
                    'description' => $module->description,
                    'category' => $module->category,
                    'duration' => $module->duration,
                    'video_url' => $module->video_url,
                    'qr_code' => $module->qr_code,
                    'content' => $module->content,
                    // Assignment information if exists
                    'assignment_id' => $assignment?->id,
                    'assignment_status' => $assignment?->status ?? 'not_assigned',
                    'assigned_at' => $assignment?->assigned_at?->toISOString(),
                    'unlocked_at' => $assignment?->unlocked_at?->toISOString(),
                    'started_at' => $assignment?->started_at?->toISOString(),
                    'completed_at' => $assignment?->completed_at?->toISOString(),
                    'progress' => $assignment ? $this->calculateProgress($assignment) : 0,
                    'is_overdue' => $assignment ? $this->isOverdue($assignment) : false,
                    'can_unlock' => $assignment ? $this->canUnlock($assignment) : false,
                ];
            });

        return [
            'assignments' => $formattedAssignments,
            'modules' => $allModules,
            'stats' => $stats,
            'statistics' => $stats // Alias for compatibility
        ];
    }

    /**
     * Unlock training module via QR code
     */
    public function unlockTrainingViaQR(string $employeeId, string $qrCode): array
    {
        $employee = Employee::find($employeeId);
        if (!$employee) {
            throw new \Exception('Employee not found');
        }

        // Find the training module with this QR code
        $module = TrainingModule::where('qr_code', $qrCode)
            ->where('active', true)
            ->first();

        if (!$module) {
            throw new \Exception('Invalid QR code or training module not found');
        }

        // Check if employee has this training assigned
        $assignment = TrainingAssignment::where('employee_id', $employeeId)
            ->where('module_id', $module->id)
            ->whereNotIn('status', ['removed', 'completed'])
            ->first();

        if (!$assignment) {
            throw new \Exception('Training module not assigned to this employee');
        }

        // Check if already unlocked
        if ($assignment->status === 'unlocked' || $assignment->status === 'in_progress') {
            return [
                'message' => 'Training module already unlocked',
                'assignment' => $this->formatAssignment($assignment),
                'module_content' => $this->formatModuleContent($module)
            ];
        }

        return DB::transaction(function () use ($assignment, $module) {
            // Update assignment status to unlocked
            $assignment->update([
                'status' => 'unlocked',
                'unlocked_at' => now()
            ]);

            return [
                'message' => 'Training module successfully unlocked',
                'assignment' => $this->formatAssignment($assignment->fresh()),
                'module_content' => $this->formatModuleContent($module)
            ];
        });
    }

    /**
     * Get training module content for employee
     */
    public function getModuleContent(string $employeeId, string $moduleId): array
    {
        $employee = Employee::find($employeeId);
        if (!$employee) {
            throw new \Exception('Employee not found');
        }

        $module = TrainingModule::where('id', $moduleId)
            ->where('active', true)
            ->first();

        if (!$module) {
            throw new \Exception('Training module not found');
        }

        // Check if employee has access to this module
        $assignment = TrainingAssignment::where('employee_id', $employeeId)
            ->where('module_id', $moduleId)
            ->whereIn('status', ['unlocked', 'in_progress', 'completed'])
            ->first();

        if (!$assignment) {
            throw new \Exception('Access denied. Training module must be unlocked first.');
        }

        // Update status to in_progress if not already
        if ($assignment->status === 'unlocked') {
            $assignment->update([
                'status' => 'in_progress',
                'started_at' => now()
            ]);
        }
        
        // Always create/update progress session when content is accessed
        // This ensures real progress tracking in the database
        $activeProgress = TrainingProgress::where('assignment_id', $assignment->id)
            ->where('employee_id', $employeeId)
            ->active()
            ->first();
            
        if (!$activeProgress) {
            TrainingProgress::startSession(
                $assignment->id,
                $employeeId,
                $moduleId,
                ['training_started' => true, 'access_time' => now()->toISOString()]
            );
        }

        return [
            'assignment' => $this->formatAssignment($assignment->fresh()),
            'module_content' => $this->formatModuleContent($module)
        ];
    }

    /**
     * Complete training module
     */
    public function completeTraining(string $employeeId, string $moduleId, array $completionData = []): array
    {
        $employee = Employee::find($employeeId);
        if (!$employee) {
            throw new \Exception('Employee not found');
        }

        // Check if module exists and is active
        $module = TrainingModule::where('id', $moduleId)->where('active', true)->first();
        if (!$module) {
            throw new \Exception('Training module not found or inactive');
        }

        $assignment = TrainingAssignment::where('employee_id', $employeeId)
            ->where('module_id', $moduleId)
            ->whereIn('status', ['unlocked', 'in_progress'])
            ->first();

        if (!$assignment) {
            throw new \Exception('Training assignment not found or not in progress');
        }

        return DB::transaction(function () use ($assignment, $completionData, $employeeId, $moduleId) {
            $updatedAssignment = $assignment->update([
                'status' => 'completed',
                'completed_at' => now(),
                'completion_data' => $completionData
            ]);
            
            if (!$updatedAssignment) {
                throw new \Exception('Failed to update training assignment');
            }
            
            // Ensure at least one progress session exists; if none, create one now so DB reflects completion
            $activeProgress = TrainingProgress::where('assignment_id', $assignment->id)
                ->where('employee_id', $employeeId)
                ->active()
                ->get();

            if ($activeProgress->isEmpty()) {
                // Create a progress session so training completion is recorded in progress table
                $newProgress = TrainingProgress::startSession(
                    $assignment->id,
                    $employeeId,
                    $moduleId,
                    array_merge($completionData, ['created_on_completion' => true])
                );
                $activeProgress = collect([$newProgress]);
            }
                
            foreach ($activeProgress as $progress) {
                $progress->endSession($completionData['time_spent_minutes'] ?? null);
                $progress->updateProgress(array_merge(
                    $completionData,
                    ['completion_time' => now()->toISOString()]
                ));
            }

            return [
                'message' => 'Training completed successfully',
                'assignment' => $this->formatAssignment($assignment->fresh())
            ];
        });
    }

    /**
     * Get employee training statistics
     */
    public function getEmployeeTrainingStats(string $employeeId): array
    {
        $assignments = TrainingAssignment::where('employee_id', $employeeId)
            ->whereHas('module', function ($query) {
                $query->where('active', true);
            })
            ->get();

        return $this->calculateEmployeeStats($assignments);
    }

    /**
     * Generate QR code for a training module
     */
    public function generateTrainingQR(string $moduleId): string
    {
        $module = TrainingModule::find($moduleId);
        if (!$module) {
            throw new \Exception('Training module not found');
        }

        // Generate new QR code if it doesn't exist
        if (empty($module->qr_code)) {
            $qrCode = $this->generateUniqueQRCode();
            $module->update(['qr_code' => $qrCode]);
        }

        $qrData = [
            'module_id' => $module->id,
            'qr_code' => $module->qr_code,
            'title' => $module->title,
            'timestamp' => now()->toISOString()
        ];

        return QrCode::size(300)->generate(json_encode($qrData));
    }

    /**
     * Format assignment for API response
     */
    private function formatAssignment(TrainingAssignment $assignment): array
    {
        $assignment->load('module');
        
        return [
            'id' => $assignment->id,
            'module' => [
                'id' => $assignment->module->id,
                'title' => $assignment->module->title,
                'description' => $assignment->module->description,
                'category' => $assignment->module->category,
                'duration' => $assignment->module->duration,
                'video_url' => $assignment->module->video_url,
                'qr_code' => $assignment->module->qr_code
            ],
            'status' => $assignment->status,
            'assigned_at' => $assignment->assigned_at->toISOString(),
            'due_date' => $assignment->due_date?->toISOString(),
            'unlocked_at' => $assignment->unlocked_at?->toISOString(),
            'started_at' => $assignment->started_at?->toISOString(),
            'completed_at' => $assignment->completed_at?->toISOString(),
            'progress' => $this->calculateProgress($assignment),
            'is_overdue' => $this->isOverdue($assignment),
            'notes' => $assignment->notes
        ];
    }

    /**
     * Get module content for display
     */
    private function formatModuleContent(TrainingModule $module): array
    {
        return [
            'id' => $module->id,
            'title' => $module->title,
            'description' => $module->description,
            'content' => $module->content,
            'video_url' => $module->video_url,
            'duration' => $module->duration,
            'category' => $module->category
        ];
    }

    /**
     * Calculate assignment progress
     */
    private function calculateProgress(TrainingAssignment $assignment): int
    {
        // Completed is always 100%
        if ($assignment->status === 'completed') {
            return 100;
        }

        // Ensure module is loaded
        if (!$assignment->relationLoaded('module')) {
            $assignment->load('module');
        }

        // Derive progress from actual TrainingProgress records relative to module duration
        $totalMinutes = TrainingProgress::forAssignment($assignment->id)->sum('time_spent_minutes');
        $moduleDuration = (int) ($assignment->module->duration ?? 0);

        if ($moduleDuration > 0 && $totalMinutes > 0) {
            // Map time spent to 95% max (reserve last 5% for completion action)
            $percent = (int) round(min(95, ($totalMinutes / $moduleDuration) * 95));
            return max($percent, $assignment->status === 'in_progress' ? 15 : ($assignment->status === 'unlocked' ? 5 : 0));
        }

        // If there is at least one progress row but no duration available, show minimal progress
        $hasProgress = TrainingProgress::forAssignment($assignment->id)->exists();
        if ($hasProgress) {
            return $assignment->status === 'in_progress' ? 15 : 5; // minimal non-zero indicator
        }

        // Fallback based on status
        if ($assignment->status === 'unlocked') return 5;
        if ($assignment->status === 'in_progress') return 15;
        return 0;
    }

    /**
     * Check if assignment is overdue
     */
    private function isOverdue(TrainingAssignment $assignment): bool
    {
        if (!$assignment->due_date) {
            return false;
        }

        return $assignment->due_date->isPast() && $assignment->status !== 'completed';
    }

    /**
     * Check if training can be unlocked
     */
    private function canUnlock(TrainingAssignment $assignment): bool
    {
        return in_array($assignment->status, ['assigned', 'overdue']);
    }

    /**
     * Calculate employee training statistics using real progress data
     */
    private function calculateEmployeeStats(Collection $assignments): array
    {
        $total = $assignments->count();
        $completed = $assignments->where('status', 'completed')->count();
        $inProgress = $assignments->where('status', 'in_progress')->count();
        $overdue = $assignments->where('status', 'overdue')->count();
        $assigned = $assignments->where('status', 'assigned')->count();
        
        // Calculate overall completion percentage using actual progress from each assignment
        $totalProgressSum = 0;
        foreach ($assignments as $assignment) {
            $totalProgressSum += $this->calculateProgress($assignment);
        }
        
        $completionRate = $total > 0 ? round($totalProgressSum / $total, 1) : 0;

        return [
            'total_assigned' => $total,
            'completed' => $completed,
            'in_progress' => $inProgress,
            'overdue' => $overdue,
            'assigned' => $assigned,
            'completion_rate' => $completionRate
        ];
    }

    /**
     * Generate unique QR code
     */
    private function generateUniqueQRCode(): string
    {
        do {
            $qrCode = 'TRN-' . strtoupper(substr(uniqid(), -8));
        } while (TrainingModule::where('qr_code', $qrCode)->exists());

        return $qrCode;
    }
}