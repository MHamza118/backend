<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\TrainingModule;
use App\Models\TrainingAssignment;
use App\Models\Employee;
use App\Services\TrainingService;
use App\Http\Requests\CreateTrainingModuleRequest;
use App\Http\Requests\UpdateTrainingModuleRequest;
use App\Http\Requests\AssignTrainingModuleRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class TrainingModuleController extends Controller
{
    use ApiResponseTrait;

    protected $trainingService;

    public function __construct(TrainingService $trainingService)
    {
        $this->trainingService = $trainingService;
    }

    /**
     * Get all training modules (Admin)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['category', 'active', 'search']);
            $modules = $this->trainingService->getAllModules($filters);
            
            $statistics = [
                'total_modules' => $modules->count(),
                'active_modules' => $modules->where('active', true)->count(),
                'total_assignments' => TrainingAssignment::count(),
                'total_completions' => TrainingAssignment::where('status', 'completed')->count(),
            ];
            
            $categories = $modules->pluck('category')->unique()->values();

            return $this->successResponse([
                'modules' => $modules->map(function ($module) {
                    return [
                        'id' => $module->id,
                        'title' => $module->title,
                        'description' => $module->description,
                        'qr_code' => $module->qr_code,
                        'video_url' => $module->video_url,
                        'content' => $module->content,
                        'duration' => $module->duration,
                        'category' => $module->category,
                        'active' => $module->active,
                        'created_at' => $module->created_at->toISOString(),
                        'updated_at' => $module->updated_at->toISOString(),
                        'created_by' => $module->createdBy ? [
                            'id' => $module->createdBy->id,
                            'name' => $module->createdBy->full_name
                        ] : null,
                        'assignments_count' => $module->assignments_count ?? 0,
                        'completions_count' => $module->completions_count ?? 0
                    ];
                }),
                'statistics' => $statistics,
                'categories' => $categories
            ], 'Training modules retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve training modules: ' . $e->getMessage());
        }
    }

    /**
     * Get specific training module (Admin)
     */
    public function show(Request $request, $id): JsonResponse
    {
        try {
            $module = $this->trainingService->getModuleById($id);
            
            if (!$module) {
                return $this->notFoundResponse('Training module not found');
            }

            $assignments = $module->assignments()->with('employee')->get();
            
            return $this->successResponse([
                'module' => [
                    'id' => $module->id,
                    'title' => $module->title,
                    'description' => $module->description,
                    'qr_code' => $module->qr_code,
                    'video_url' => $module->video_url,
                    'content' => $module->content,
                    'duration' => $module->duration,
                    'category' => $module->category,
                    'active' => $module->active,
                    'created_at' => $module->created_at->toISOString(),
                    'updated_at' => $module->updated_at->toISOString(),
                    'created_by' => $module->createdBy ? [
                        'id' => $module->createdBy->id,
                        'name' => $module->createdBy->full_name
                    ] : null,
                ],
                'assignments' => $assignments->map(function ($assignment) {
                    return [
                        'id' => $assignment->id,
                        'employee' => [
                            'id' => $assignment->employee->id,
                            'name' => $assignment->employee->full_name,
                            'email' => $assignment->employee->email
                        ],
                        'status' => $assignment->status,
                        'assigned_at' => $assignment->assigned_at->toISOString(),
                        'due_date' => $assignment->due_date?->toISOString(),
                        'unlocked_at' => $assignment->unlocked_at?->toISOString(),
                        'completed_at' => $assignment->completed_at?->toISOString(),
                    ];
                })
            ], 'Training module details retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve training module: ' . $e->getMessage());
        }
    }

    /**
     * Create new training module (Admin)
     */
    public function store(CreateTrainingModuleRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $validated['created_by'] = $request->user()->id;
            
            // Generate QR code if not provided
            if (empty($validated['qr_code'])) {
                $validated['qr_code'] = $this->generateUniqueQRCode();
            }

            $module = $this->trainingService->createModule($validated);

            return $this->successResponse([
                'module' => [
                    'id' => $module->id,
                    'title' => $module->title,
                    'description' => $module->description,
                    'qr_code' => $module->qr_code,
                    'video_url' => $module->video_url,
                    'content' => $module->content,
                    'duration' => $module->duration,
                    'category' => $module->category,
                    'active' => $module->active,
                    'created_at' => $module->created_at->toISOString(),
                    'updated_at' => $module->updated_at->toISOString(),
                ]
            ], 'Training module created successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), 'Validation failed');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create training module: ' . $e->getMessage());
        }
    }

    /**
     * Update training module (Admin)
     */
    public function update(UpdateTrainingModuleRequest $request, $id): JsonResponse
    {
        try {
            $module = $this->trainingService->getModuleById($id);
            
            if (!$module) {
                return $this->notFoundResponse('Training module not found');
            }

            $validated = $request->validated();
            $module = $this->trainingService->updateModule($module, $validated);

            return $this->successResponse([
                'module' => [
                    'id' => $module->id,
                    'title' => $module->title,
                    'description' => $module->description,
                    'qr_code' => $module->qr_code,
                    'video_url' => $module->video_url,
                    'content' => $module->content,
                    'duration' => $module->duration,
                    'category' => $module->category,
                    'active' => $module->active,
                    'created_at' => $module->created_at->toISOString(),
                    'updated_at' => $module->updated_at->toISOString(),
                ]
            ], 'Training module updated successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), 'Validation failed');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update training module: ' . $e->getMessage());
        }
    }

    /**
     * Delete training module (Admin)
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        try {
            $module = $this->trainingService->getModuleById($id);
            
            if (!$module) {
                return $this->notFoundResponse('Training module not found');
            }

            // Check if module has active assignments
            $activeAssignments = $module->assignments()->whereIn('status', ['assigned', 'unlocked', 'in_progress'])->count();
            
            if ($activeAssignments > 0) {
                return $this->errorResponse('Cannot delete training module with active assignments. Please remove or complete all assignments first.');
            }

            $this->trainingService->deleteModule($module);

            return $this->successResponse(null, 'Training module deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete training module: ' . $e->getMessage());
        }
    }

    /**
     * Assign training module to employees (Admin)
     */
    public function assignToEmployees(AssignTrainingModuleRequest $request, $id): JsonResponse
    {
        try {
            $module = $this->trainingService->getModuleById($id);
            
            if (!$module) {
                return $this->notFoundResponse('Training module not found');
            }

            $validated = $request->validated();
            $result = $this->trainingService->assignModuleToEmployees($module, $validated, $request->user());

            return $this->successResponse([
                'assignments' => $result['assignments'],
                'total_assigned' => $result['total_assigned'],
                'warnings' => $result['warnings'] ?? []
            ], "Training module assigned to {$result['total_assigned']} employee(s) successfully");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), 'Validation failed');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to assign training module: ' . $e->getMessage());
        }
    }

    /**
     * Get training module assignments (Admin)
     */
    public function getAssignments(Request $request, $id): JsonResponse
    {
        try {
            $module = $this->trainingService->getModuleById($id);
            
            if (!$module) {
                return $this->notFoundResponse('Training module not found');
            }

            $assignments = $module->assignments()->with('employee')->get();
            
            return $this->successResponse([
                'assignments' => $assignments->map(function ($assignment) {
                    return [
                        'id' => $assignment->id,
                        'employee' => [
                            'id' => $assignment->employee->id,
                            'name' => $assignment->employee->full_name,
                            'email' => $assignment->employee->email
                        ],
                        'status' => $assignment->status,
                        'assigned_at' => $assignment->assigned_at->toISOString(),
                        'due_date' => $assignment->due_date?->toISOString(),
                        'unlocked_at' => $assignment->unlocked_at?->toISOString(),
                        'completed_at' => $assignment->completed_at?->toISOString(),
                    ];
                })
            ], 'Training module assignments retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve training module assignments: ' . $e->getMessage());
        }
    }

    /**
     * Reset training progress (Admin)
     */
    public function resetProgress(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'employee_ids' => 'required|array',
                'employee_ids.*' => 'exists:employees,id'
            ]);

            $module = $this->trainingService->getModuleById($id);
            
            if (!$module) {
                return $this->notFoundResponse('Training module not found');
            }

            $result = $this->trainingService->resetEmployeeProgress($module, $request->employee_ids);

            return $this->successResponse([
                'reset_count' => $result['reset_count']
            ], "Training progress reset for {$result['reset_count']} employee(s) successfully");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), 'Validation failed');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to reset training progress: ' . $e->getMessage());
        }
    }

    /**
     * Remove assignments for training module (Admin)
     */
    public function removeAssignments(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'employee_ids' => 'required|array',
                'employee_ids.*' => 'exists:employees,id'
            ]);

            $module = $this->trainingService->getModuleById($id);
            
            if (!$module) {
                return $this->notFoundResponse('Training module not found');
            }

            $removedCount = TrainingAssignment::where('module_id', $id)
                ->whereIn('employee_id', $request->employee_ids)
                ->delete();

            return $this->successResponse([
                'removed_count' => $removedCount
            ], "Removed {$removedCount} assignment(s) successfully");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), 'Validation failed');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to remove assignments: ' . $e->getMessage());
        }
    }

    /**
     * Get training categories (Admin)
     */
    public function getCategories(Request $request): JsonResponse
    {
        try {
            $categories = $this->trainingService->getCategories();
            
            return $this->successResponse(
                $categories,
                'Training categories retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve training categories: ' . $e->getMessage());
        }
    }

    /**
     * Get training analytics (Admin)
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        try {
            $analytics = $this->trainingService->getAnalytics();
            
            return $this->successResponse(
                $analytics,
                'Training analytics retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve training analytics: ' . $e->getMessage());
        }
    }

    /**
     * Generate QR code for training module (Admin)
     */
    public function generateQrCode(Request $request, $id): JsonResponse
    {
        try {
            $module = $this->trainingService->getModuleById($id);
            
            if (!$module) {
                return $this->notFoundResponse('Training module not found');
            }

            return $this->successResponse([
                'qr_code' => $module->qr_code,
                'qr_url' => url("/training/module/{$module->qr_code}")
            ], 'QR code retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to generate QR code: ' . $e->getMessage());
        }
    }

    /**
     * Regenerate QR code for training module (Admin)
     */
    public function regenerateQrCode(Request $request, $id): JsonResponse
    {
        try {
            $module = $this->trainingService->getModuleById($id);
            
            if (!$module) {
                return $this->notFoundResponse('Training module not found');
            }

            $newQRCode = $this->generateUniqueQRCode();
            $module = $this->trainingService->updateModule($module, ['qr_code' => $newQRCode]);

            return $this->successResponse([
                'qr_code' => $module->qr_code,
                'qr_url' => url("/training/module/{$module->qr_code}")
            ], 'QR code regenerated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to regenerate QR code: ' . $e->getMessage());
        }
    }

    /**
     * Generate unique QR code
     */
    private function generateUniqueQRCode(): string
    {
        do {
            $qrCode = 'TRAIN_MODULE_' . strtoupper(Str::random(8));
        } while (TrainingModule::where('qr_code', $qrCode)->exists());

        return $qrCode;
    }

}