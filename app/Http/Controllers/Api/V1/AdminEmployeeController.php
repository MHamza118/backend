<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeResource;
use App\Http\Resources\QuestionnaireResource;
use App\Models\Questionnaire;
use App\Services\EmployeeService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
}
