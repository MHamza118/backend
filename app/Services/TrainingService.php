<?php

namespace App\Services;

use App\Models\TrainingModule;
use App\Models\TrainingAssignment;
use App\Models\TrainingProgress;
use App\Models\Employee;
use App\Models\Admin;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TrainingService
{
    /**
     * Get all training modules with filters
     */
    public function getAllModules(array $filters = []): Collection
    {
        $query = TrainingModule::with(['createdBy'])
            ->withCount([
                'assignments',
                'assignments as completions_count' => function ($query) {
                    $query->where('status', 'completed');
                }
            ]);

        if (isset($filters['category']) && !empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (isset($filters['active'])) {
            $query->where('active', $filters['active']);
        }

        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get training module by ID
     */
    public function getModuleById(string $id): ?TrainingModule
    {
        return TrainingModule::with(['createdBy', 'assignments.employee'])->find($id);
    }

    /**
     * Create new training module
     */
    public function createModule(array $data): TrainingModule
    {
        return DB::transaction(function () use ($data) {
            return TrainingModule::create([
                'title' => $data['title'],
                'description' => $data['description'],
                'qr_code' => $data['qr_code'],
                'video_url' => $data['video_url'] ?? null,
                'content' => $data['content'],
                'duration' => $data['duration'] ?? null,
                'category' => $data['category'],
                'active' => $data['active'] ?? true,
                'created_by' => $data['created_by']
            ]);
        });
    }

    /**
     * Update training module
     */
    public function updateModule(TrainingModule $module, array $data): TrainingModule
    {
        return DB::transaction(function () use ($module, $data) {
            $module->update(array_filter([
                'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
                'qr_code' => $data['qr_code'] ?? null,
                'video_url' => $data['video_url'] ?? null,
                'content' => $data['content'] ?? null,
                'duration' => $data['duration'] ?? null,
                'category' => $data['category'] ?? null,
                'active' => isset($data['active']) ? $data['active'] : null
            ], function ($value) {
                return $value !== null;
            }));

            return $module->fresh();
        });
    }

    /**
     * Delete training module
     */
    public function deleteModule(TrainingModule $module): bool
    {
        return DB::transaction(function () use ($module) {
            // Delete associated assignments first
            $module->assignments()->delete();
            
            // Delete the module
            return $module->delete();
        });
    }

    /**
     * Assign training module to employees
     */
    public function assignModuleToEmployees(TrainingModule $module, array $data, Admin $assignedBy): array
    {
        return DB::transaction(function () use ($module, $data, $assignedBy) {
            $employeeIds = $data['employee_ids'];
            $dueDate = isset($data['due_date']) ? Carbon::parse($data['due_date']) : null;
            $notes = $data['notes'] ?? null;

            $assignments = [];
            $warnings = [];
            $totalAssigned = 0;

            foreach ($employeeIds as $employeeId) {
                $employee = Employee::find($employeeId);
                
                if (!$employee) {
                    $warnings[] = "Employee with ID {$employeeId} not found";
                    continue;
                }

                if ($employee->status !== Employee::STATUS_APPROVED) {
                    $warnings[] = "Employee {$employee->full_name} is not approved yet";
                    continue;
                }

                // Check if already assigned
                $existingAssignment = TrainingAssignment::where('employee_id', $employeeId)
                    ->where('module_id', $module->id)
                    ->whereNotIn('status', ['removed'])
                    ->first();

                if ($existingAssignment) {
                    $warnings[] = "Employee {$employee->full_name} is already assigned to this training";
                    continue;
                }

                // Create new assignment
                $assignment = TrainingAssignment::create([
                    'module_id' => $module->id,
                    'employee_id' => $employeeId,
                    'assigned_by' => $assignedBy->id,
                    'assigned_at' => now(),
                    'due_date' => $dueDate,
                    'status' => 'assigned',
                    'notes' => $notes
                ]);

                $assignments[] = [
                    'id' => $assignment->id,
                    'employee' => [
                        'id' => $employee->id,
                        'name' => $employee->full_name,
                        'email' => $employee->email
                    ],
                    'status' => $assignment->status,
                    'assigned_at' => $assignment->assigned_at->toISOString(),
                    'due_date' => $assignment->due_date?->toISOString()
                ];

                $totalAssigned++;
            }

            return [
                'assignments' => $assignments,
                'total_assigned' => $totalAssigned,
                'warnings' => $warnings
            ];
        });
    }

    /**
     * Reset employee training progress
     */
    public function resetEmployeeProgress(TrainingModule $module, array $employeeIds): array
    {
        return DB::transaction(function () use ($module, $employeeIds) {
            $resetCount = 0;

            foreach ($employeeIds as $employeeId) {
                $assignment = TrainingAssignment::where('employee_id', $employeeId)
                    ->where('module_id', $module->id)
                    ->first();

                if ($assignment) {
                    $assignment->update([
                        'status' => 'assigned',
                        'unlocked_at' => null,
                        'started_at' => null,
                        'completed_at' => null,
                        'completion_data' => null,
                        'reset_count' => ($assignment->reset_count ?? 0) + 1,
                        'last_reset_at' => now()
                    ]);
                    
                    // Delete all progress records for this assignment
                    TrainingProgress::where('assignment_id', $assignment->id)
                        ->where('employee_id', $employeeId)
                        ->delete();
                    
                    $resetCount++;
                }
            }

            return [
                'reset_count' => $resetCount
            ];
        });
    }

    /**
     * Get training categories
     */
    public function getCategories(): array
    {
        $categories = TrainingModule::distinct()
            ->whereNotNull('category')
            ->pluck('category')
            ->filter()
            ->values()
            ->toArray();

        // Add default categories if they don't exist
        $defaultCategories = ['Safety', 'Service', 'Technology', 'Operations', 'Leadership', 'Other'];
        
        return array_values(array_unique(array_merge($categories, $defaultCategories)));
    }

    /**
     * Get training analytics
     */
    public function getAnalytics(): array
    {
        $totalModules = TrainingModule::count();
        $activeModules = TrainingModule::where('active', true)->count();
        $totalEmployees = Employee::where('status', Employee::STATUS_APPROVED)->count();
        $totalAssignments = TrainingAssignment::count();
        $completedAssignments = TrainingAssignment::where('status', 'completed')->count();

        $completionRate = $totalAssignments > 0 ? round(($completedAssignments / $totalAssignments) * 100, 1) : 0;

        // Recent activity (last 10 training completions)
        $recentActivity = TrainingAssignment::where('status', 'completed')
            ->with(['employee', 'module'])
            ->orderBy('completed_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($assignment) {
                return [
                    'type' => 'completion',
                    'employee_name' => $assignment->employee->full_name,
                    'module_title' => $assignment->module->title,
                    'timestamp' => $assignment->completed_at->toISOString()
                ];
            });

        // Overdue training
        $overdueTraining = TrainingAssignment::whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->whereNotIn('status', ['completed'])
            ->with(['employee', 'module'])
            ->get()
            ->map(function ($assignment) {
                return [
                    'employee_id' => $assignment->employee->id,
                    'employee_name' => $assignment->employee->full_name,
                    'module_title' => $assignment->module->title,
                    'due_date' => $assignment->due_date->toISOString(),
                    'days_overdue' => now()->diffInDays($assignment->due_date)
                ];
            });

        return [
            'overview' => [
                'total_modules' => $totalModules,
                'active_modules' => $activeModules,
                'total_employees' => $totalEmployees,
                'total_assignments' => $totalAssignments,
                'completion_rate' => $completionRate
            ],
            'recent_activity' => $recentActivity,
            'overdue_training' => $overdueTraining
        ];
    }

    /**
     * Update assignment status based on due dates
     */
    public function updateOverdueAssignments(): int
    {
        return TrainingAssignment::whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->whereIn('status', ['assigned', 'unlocked', 'in_progress'])
            ->update(['status' => 'overdue']);
    }
}