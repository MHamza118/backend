<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeResource;
use App\Http\Resources\QuestionnaireResource;
use App\Models\Questionnaire;
use App\Models\EmployeeFileUpload;
use App\Services\EmployeeService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AdminEmployeeController extends Controller
{
    use ApiResponseTrait;

    protected $employeeService;

    public function __construct(EmployeeService $employeeService)
    {
        $this->employeeService = $employeeService;
        // Add middleware for admin-only access in routes
    }

    /**
     * Get all employees with filters
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['status', 'stage', 'department', 'search', 'sort_by', 'sort_direction']);
            $perPage = $request->get('per_page', 15);

            $employees = $this->employeeService->getAllEmployees($filters, $perPage);

            return $this->successResponse([
                'employees' => EmployeeResource::collection($employees->items()),
                'pagination' => [
                    'current_page' => $employees->currentPage(),
                    'last_page' => $employees->lastPage(),
                    'per_page' => $employees->perPage(),
                    'total' => $employees->total()
                ]
            ], 'Employees retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve employees: ' . $e->getMessage());
        }
    }

    /**
     * Get employees pending approval
     */
    public function pendingApproval(): JsonResponse
    {
        try {
            $employees = $this->employeeService->getPendingApprovalEmployees();

            return $this->successResponse(
                EmployeeResource::collection($employees),
                'Pending approval employees retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve pending employees: ' . $e->getMessage());
        }
    }

    /**
     * Show specific employee
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $profileData = $this->employeeService->getProfile($id);

            if (empty($profileData)) {
                return $this->notFoundResponse('Employee not found');
            }

            return $this->successResponse($profileData, 'Employee details retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve employee: ' . $e->getMessage());
        }
    }

    /**
     * Approve employee
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'note' => 'nullable|string|max:500'
        ]);

        try {
            $employee = $this->employeeService->approveEmployee($id, $request->user()->id);

            return $this->successResponse(
                new EmployeeResource($employee),
                'Employee approved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to approve employee: ' . $e->getMessage());
        }
    }

    /**
     * Reject employee
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:1000'
        ]);

        try {
            $employee = $this->employeeService->rejectEmployee(
                $id,
                $request->rejection_reason,
                $request->user()->id
            );

            return $this->successResponse(
                new EmployeeResource($employee),
                'Employee rejected successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to reject employee: ' . $e->getMessage());
        }
    }

    /**
     * Get employee statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = $this->employeeService->getEmployeeStatistics();

            return $this->successResponse($stats, 'Employee statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve statistics: ' . $e->getMessage());
        }
    }

    /**
     * Pause an approved employee (no dashboard access while paused)
     */
    public function pause(Request $request, string $id): JsonResponse
    {
        $request->validate(['reason' => 'nullable|string|max:500']);
        try {
            $employee = $this->employeeService->pauseEmployee($id, $request->get('reason'));
            if (!$employee) {
                return $this->notFoundResponse('Employee not found');
            }
            return $this->successResponse(new EmployeeResource($employee), 'Employee paused successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to pause employee: ' . $e->getMessage());
        }
    }

    /**
     * Resume a paused employee
     */
    public function resume(Request $request, string $id): JsonResponse
    {
        try {
            $employee = $this->employeeService->resumeEmployee($id);
            if (!$employee) {
                return $this->notFoundResponse('Employee not found');
            }
            return $this->successResponse(new EmployeeResource($employee), 'Employee resumed successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to resume employee: ' . $e->getMessage());
        }
    }

    /**
     * Deactivate employee (mark inactive)
     */
    public function deactivate(Request $request, string $id): JsonResponse
    {
        $request->validate(['reason' => 'nullable|string|max:500']);
        try {
            $employee = $this->employeeService->deactivateEmployee($id, $request->get('reason'));
            if (!$employee) {
                return $this->notFoundResponse('Employee not found');
            }
            return $this->successResponse(new EmployeeResource($employee), 'Employee marked inactive successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to deactivate employee: ' . $e->getMessage());
        }
    }

    /**
     * Activate an inactive employee (restore access if stage active)
     */
    public function activate(Request $request, string $id): JsonResponse
    {
        try {
            $employee = $this->employeeService->activateEmployee($id);
            if (!$employee) {
                return $this->notFoundResponse('Employee not found');
            }
            return $this->successResponse(new EmployeeResource($employee), 'Employee activated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to activate employee: ' . $e->getMessage());
        }
    }

    /**
     * Get all questionnaires
     */
    public function questionnaires(Request $request): JsonResponse
    {
        try {
            $questionnaires = Questionnaire::when($request->get('active_only'), function ($query) {
                return $query->active();
            })
            ->with('createdBy')
            ->ordered()
            ->get();

            return $this->successResponse(
                QuestionnaireResource::collection($questionnaires),
                'Questionnaires retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve questionnaires: ' . $e->getMessage());
        }
    }

    /**
     * Create new questionnaire
     */
    public function createQuestionnaire(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'questions' => 'required|array',
            'questions.*.question' => 'required|string',
            'questions.*.type' => 'required|in:text,multiple_choice,single_choice,boolean',
            'questions.*.options' => 'required_if:questions.*.type,multiple_choice,single_choice|array',
            'questions.*.required' => 'boolean',
            'is_active' => 'boolean',
            'order_index' => 'integer|min:0'
        ]);

        try {
            $questionnaire = Questionnaire::create([
                'title' => $request->title,
                'description' => $request->description,
                'questions' => $request->questions,
                'is_active' => $request->get('is_active', true),
                'order_index' => $request->get('order_index', 0),
                'created_by' => $request->user()->id
            ]);

            return $this->successResponse(
                new QuestionnaireResource($questionnaire),
                'Questionnaire created successfully',
                201
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), 'Questionnaire creation failed');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create questionnaire: ' . $e->getMessage());
        }
    }

    /**
     * Update questionnaire
     */
    public function updateQuestionnaire(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'questions' => 'sometimes|array',
            'questions.*.question' => 'required|string',
            'questions.*.type' => 'required|in:text,multiple_choice,single_choice,boolean',
            'questions.*.options' => 'required_if:questions.*.type,multiple_choice,single_choice|array',
            'questions.*.required' => 'boolean',
            'is_active' => 'boolean',
            'order_index' => 'integer|min:0'
        ]);

        try {
            $questionnaire = Questionnaire::findOrFail($id);
            $questionnaire->update($request->only([
                'title', 'description', 'questions', 'is_active', 'order_index'
            ]));

            return $this->successResponse(
                new QuestionnaireResource($questionnaire),
                'Questionnaire updated successfully'
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), 'Questionnaire update failed');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update questionnaire: ' . $e->getMessage());
        }
    }

    /**
     * Delete questionnaire
     */
    public function deleteQuestionnaire(string $id): JsonResponse
    {
        try {
            $questionnaire = Questionnaire::findOrFail($id);
            $questionnaire->delete();

            return $this->successResponse(null, 'Questionnaire deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete questionnaire: ' . $e->getMessage());
        }
    }

    /**
     * Get questionnaire by ID
     */
    public function showQuestionnaire(string $id): JsonResponse
    {
        try {
            $questionnaire = Questionnaire::with('createdBy')->findOrFail($id);

            return $this->successResponse(
                new QuestionnaireResource($questionnaire),
                'Questionnaire retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve questionnaire: ' . $e->getMessage());
        }
    }

    /**
     * Toggle questionnaire active status
     */
    public function toggleQuestionnaireStatus(string $id): JsonResponse
    {
        try {
            $questionnaire = Questionnaire::findOrFail($id);
            $questionnaire->update(['is_active' => !$questionnaire->is_active]);

            $status = $questionnaire->is_active ? 'activated' : 'deactivated';

            return $this->successResponse(
                new QuestionnaireResource($questionnaire),
                "Questionnaire {$status} successfully"
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to toggle questionnaire status: ' . $e->getMessage());
        }
    }

    /**
     * Download an employee file upload
     */
    public function downloadFile(Request $request, string $id)
    {
        try {
            // Find the file record in the database
            $file = EmployeeFileUpload::findOrFail($id);
            
            // Use the 'public' disk since files are stored there
            $disk = Storage::disk('public');
            
            // Check if file exists in storage
            if (!$disk->exists($file->file_path)) {
                return response()->json([
                    'error' => 'File not found in storage',
                    'path' => $file->file_path
                ], 404);
            }
            
            // Use Laravel's download response for proper binary file handling
            return $disk->download(
                $file->file_path,
                $file->original_filename,
                [
                    'Content-Type' => $file->mime_type ?: 'application/octet-stream',
                    'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                    'Pragma' => 'public',
                    'Expires' => '0'
                ]
            );
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to download file: ' . $e->getMessage()
            ], 500);
        }
    }
}
