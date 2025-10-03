<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Questionnaire;
use App\Repositories\Contracts\EmployeeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class EmployeeService
{
    protected $employeeRepository;

    public function __construct(EmployeeRepositoryInterface $employeeRepository)
    {
        $this->employeeRepository = $employeeRepository;
    }

    /**
     * Register new employee
     */
    public function register(array $data): Employee
    {
        // Check if employee already exists
        if ($this->employeeRepository->findByEmail($data['email'])) {
            throw ValidationException::withMessages([
                'email' => ['An employee with this email already exists.']
            ]);
        }

        // Set initial stage and status
        $data['stage'] = Employee::STAGE_INTERVIEW;
        $data['status'] = Employee::STATUS_PENDING_APPROVAL;
        $data['password'] = Hash::make($data['password']);

        return $this->employeeRepository->create($data);
    }

    /**
     * Authenticate employee login only - rejects admin users
     */
    public function login(string $email, string $password): array
    {
        // First check if this email belongs to an admin (should not be allowed)
        $admin = \App\Models\Admin::where('email', $email)->first();
        if ($admin) {
            throw ValidationException::withMessages([
                'email' => ['This email is registered as an admin/manager. Please use the Admin Login form.'],
            ]);
        }

        $employee = $this->employeeRepository->findByEmail($email);

        if (!$employee || !Hash::check($password, $employee->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid employee credentials. Please check your email and password.']
            ]);
        }

        // Check if employee account is paused or inactive
        if ($employee->isPaused()) {
            throw ValidationException::withMessages([
                'email' => ['Your account is currently paused. Please contact your manager for assistance.']
            ]);
        }

        if ($employee->isInactive()) {
            throw ValidationException::withMessages([
                'email' => ['Your account is inactive. Please contact your manager for assistance.']
            ]);
        }

        if ($employee->status === Employee::STATUS_REJECTED) {
            throw ValidationException::withMessages([
                'email' => ['Your application has been rejected. Please contact HR for more information.']
            ]);
        }

        // Load training-related relationships
        $employee->load([
            'trainingAssignments.module', 
            'trainingProgress'
        ]);

        $token = $employee->createToken('employee-token')->plainTextToken;

        return [
            'employee' => $employee,
            'token' => $token,
            'stage' => $employee->stage,
            'status' => $employee->status,
            'can_access_dashboard' => $employee->canAccessDashboard()
        ];
    }

    /**
     * Get employee profile with stage info
     */
    public function getProfile(string $employeeId): array
    {
        $employee = $this->employeeRepository->findById($employeeId);
        if (!$employee) {
            return [];
        }

        // Load training-related relationships and file uploads
        $employee->load([
            'trainingAssignments.module', 
            'trainingProgress',
            'fileUploads'
        ]);

        // Build complete profile data combining registration info and profile data
        $profileData = $employee->profile_data ?? [];
        $questionnaireResponses = $employee->questionnaire_responses ?? [];
        
        // Extract personal info from various sources
        $personalInfo = [];
        
        // From registration (primary source)
        $personalInfo['first_name'] = $employee->first_name;
        $personalInfo['last_name'] = $employee->last_name;
        $personalInfo['email'] = $employee->email;
        $personalInfo['phone'] = $employee->phone;
        
        // From profile_data if exists
        if (isset($profileData['personal_info'])) {
            $personalInfo = array_merge($personalInfo, $profileData['personal_info']);
        }
        
        // From questionnaire responses (fallback/additional info)
        if (!empty($questionnaireResponses)) {
            // Question 1 is Full Name, Question 2 is Phone, Question 3 is Email based on seeder
            foreach ($questionnaireResponses as $index => $response) {
                if (is_array($response) && isset($response['question']) && isset($response['answer'])) {
                    $question = strtolower($response['question']);
                    $answer = $response['answer'];
                    
                    if (str_contains($question, 'full name') && !empty($answer)) {
                        $names = explode(' ', $answer, 2);
                        if (empty($personalInfo['first_name']) && count($names) > 0) {
                            $personalInfo['first_name'] = $names[0];
                        }
                        if (empty($personalInfo['last_name']) && count($names) > 1) {
                            $personalInfo['last_name'] = $names[1];
                        }
                    } elseif (str_contains($question, 'phone') && !empty($answer) && empty($personalInfo['phone'])) {
                        $personalInfo['phone'] = $answer;
                    } elseif (str_contains($question, 'email') && !empty($answer) && $personalInfo['email'] !== $answer) {
                        // Only override if different from registration email
                        $personalInfo['questionnaire_email'] = $answer;
                    }
                }
            }
        }
        
        return [
            'employee' => $employee,
            'personal_info' => $personalInfo,
            'questionnaire_files' => $employee->fileUploads,
            'stage' => $employee->stage,
            'status' => $employee->status,
            'can_access_dashboard' => $employee->canAccessDashboard(),
            'next_stage' => $employee->getNextStage(),
            'stage_info' => $this->getStageInfo($employee->stage)
        ];
    }

    /**
     * Generate QR code for interview stage
     */
    public function generateInterviewQR(string $employeeId): string
    {
        $employee = $this->employeeRepository->findById($employeeId);
        if (!$employee || $employee->stage !== Employee::STAGE_INTERVIEW) {
            throw new \Exception('Employee not in interview stage');
        }

        // Generate QR code with employee data and timestamp
        $qrData = [
            'employee_id' => $employee->id,
            'stage' => 'interview',
            'timestamp' => now()->toISOString(),
            'location_url' => url("/employee/select-location/{$employee->id}")
        ];

        return QrCode::size(300)->generate(json_encode($qrData));
    }

    /**
     * Submit location and move to next stage
     */
    public function submitLocation(string $employeeId, string $location): Employee
    {
        $employee = $this->employeeRepository->findById($employeeId);
        if (!$employee || $employee->stage !== Employee::STAGE_INTERVIEW) {
            throw new \Exception('Employee not in correct stage');
        }

        return $this->employeeRepository->updateLocation($employeeId, $location);
    }

    /**
     * Get questionnaire for employee
     */
    public function getQuestionnaire(string $employeeId): array
    {
        $employee = $this->employeeRepository->findById($employeeId);
        if (!$employee) {
            throw new \Exception('Employee not found');
        }

        // Auto-progress employee if needed
        $employee = $this->ensureEmployeeCanAccessQuestionnaire($employeeId);

        // Handle stage transitions more gracefully
        switch ($employee->stage) {
            case Employee::STAGE_LOCATION_SELECTED:
                // Correct stage for questionnaire access
                break;
                
            case Employee::STAGE_QUESTIONNAIRE_COMPLETED:
                // Allow re-access to view completed questionnaire
                break;
                
            default:
                throw new \Exception('Employee not in correct stage for questionnaire access');
        }

        $questionnaire = Questionnaire::active()->ordered()->first();
        if (!$questionnaire) {
            throw new \Exception('No active questionnaire found');
        }

        return [
            'questionnaire' => $questionnaire,
            'employee' => $employee
        ];
    }

    /**
     * Submit questionnaire responses
     */
    public function submitQuestionnaire(string $employeeId, array $responses, array $uploadedFiles = []): Employee
    {
        $employee = $this->employeeRepository->findById($employeeId);
        if (!$employee) {
            throw new \Exception('Employee not found');
        }

        // Allow questionnaire submission from both location_selected and questionnaire_completed stages
        if (!in_array($employee->stage, [Employee::STAGE_LOCATION_SELECTED, Employee::STAGE_QUESTIONNAIRE_COMPLETED])) {
            throw new \Exception('Employee not in correct stage for questionnaire submission');
        }

        // Prevent duplicate submission by checking if responses already exist and stage is completed
        if ($employee->stage === Employee::STAGE_QUESTIONNAIRE_COMPLETED && $employee->questionnaire_responses) {
            // Allow update but log it
            \Log::info('Employee attempting to resubmit questionnaire', [
                'employee_id' => $employeeId,
                'previous_responses' => $employee->questionnaire_responses,
                'new_responses' => $responses,
                'uploaded_files' => array_keys($uploadedFiles)
            ]);
        }

        // If files were uploaded, include them in the response data
        if (!empty($uploadedFiles)) {
            foreach ($uploadedFiles as $questionIndex => $fileUpload) {
                // Update the response to include file information
                if (isset($responses[$questionIndex])) {
                    $responses[$questionIndex]['file_upload_id'] = $fileUpload->id;
                    $responses[$questionIndex]['file_path'] = $fileUpload->file_path;
                    $responses[$questionIndex]['original_filename'] = $fileUpload->original_filename;
                }
            }
        }

        return $this->employeeRepository->saveQuestionnaireResponses($employeeId, $responses);
    }

    /**
     * Auto-progress employee through stages when needed
     */
    public function ensureEmployeeCanAccessQuestionnaire(string $employeeId): Employee
    {
        $employee = $this->employeeRepository->findById($employeeId);
        if (!$employee) {
            throw new \Exception('Employee not found');
        }

        // If employee is still in interview stage, move them to location_selected
        // This handles cases where the location selection step was skipped or not completed
        if ($employee->stage === Employee::STAGE_INTERVIEW) {
            // Set a default location if none exists
            $defaultLocation = $employee->location ?? 'Main Location';
            $employee = $this->employeeRepository->updateLocation($employeeId, $defaultLocation);
        }

        return $employee;
    }

    /**
     * Get all employees with filters (Admin only)
     */
    public function getAllEmployees(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->employeeRepository->getAllWithFilters($filters, $perPage);
    }

    /**
     * Get pending approval employees (Admin only)
     */
    public function getPendingApprovalEmployees(): Collection
    {
        return $this->employeeRepository->getPendingApproval();
    }

    /**
     * Approve employee (Admin only)
     */
    public function approveEmployee(string $employeeId, string $adminId): Employee
    {
        $employee = $this->employeeRepository->approve($employeeId, $adminId);
        if (!$employee) {
            throw new \Exception('Employee not found or already processed');
        }

        return $employee;
    }

    /**
     * Reject employee (Admin only)
     */
    public function rejectEmployee(string $employeeId, string $rejectionReason, string $adminId): Employee
    {
        $employee = $this->employeeRepository->reject($employeeId, $rejectionReason, $adminId);
        if (!$employee) {
            throw new \Exception('Employee not found');
        }
        return $employee;
    }

    // Lifecycle operations
    public function pauseEmployee(string $employeeId, ?string $reason = null): ?Employee
    {
        return $this->employeeRepository->pause($employeeId, $reason);
    }

    public function resumeEmployee(string $employeeId): ?Employee
    {
        return $this->employeeRepository->resume($employeeId);
    }

    public function deactivateEmployee(string $employeeId, ?string $reason = null): ?Employee
    {
        return $this->employeeRepository->deactivate($employeeId, $reason);
    }

    public function activateEmployee(string $employeeId): ?Employee
    {
        return $this->employeeRepository->activate($employeeId);
    }

    /**
     * Update employee personal information
     */
    public function updatePersonalInfo(string $employeeId, array $personalInfo): Employee
    {
        $employee = $this->employeeRepository->findById($employeeId);
        if (!$employee) {
            throw new \Exception('Employee not found');
        }

        // Validate that employee is approved and active (can edit personal info)
        if (!$employee->canAccessDashboard()) {
            throw new \Exception('Employee must be approved and active to update personal information');
        }

        $updatedEmployee = $this->employeeRepository->updatePersonalInfo($employeeId, $personalInfo);
        if (!$updatedEmployee) {
            throw new \Exception('Failed to update personal information');
        }

        return $updatedEmployee;
    }

    /**
     * Get employee statistics (Admin only)
     */
    public function getEmployeeStatistics(): array
    {
        return $this->employeeRepository->getStatistics();
    }

    /**
     * Get stage information
     */
    private function getStageInfo(string $stage): array
    {
        $stageInfo = [
            Employee::STAGE_INTERVIEW => [
                'title' => 'Interview Stage',
                'description' => 'Scan the QR code to proceed to location selection',
                'action' => 'Generate QR Code',
                'next_step' => 'Select your preferred work location'
            ],
            Employee::STAGE_LOCATION_SELECTED => [
                'title' => 'Questionnaire Stage', 
                'description' => 'Complete the onboarding questionnaire',
                'action' => 'Fill Questionnaire',
                'next_step' => 'Wait for admin approval'
            ],
            Employee::STAGE_QUESTIONNAIRE_COMPLETED => [
                'title' => 'Welcome Stage',
                'description' => 'Welcome! Your application is under review',
                'action' => 'Wait for Approval',
                'next_step' => 'Admin will review your application'
            ],
            Employee::STAGE_ACTIVE => [
                'title' => 'Active Employee',
                'description' => 'Welcome to the team! You now have full access',
                'action' => 'Access Dashboard',
                'next_step' => 'Start working'
            ]
        ];

        return $stageInfo[$stage] ?? [];
    }
}
