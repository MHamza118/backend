<?php

namespace App\Repositories;

use App\Models\Employee;
use App\Repositories\Contracts\EmployeeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class EmployeeRepository implements EmployeeRepositoryInterface
{
    protected $model;

    public function __construct(Employee $model)
    {
        $this->model = $model;
    }

    public function findById(string $id): ?Employee
    {
        return $this->model->find($id);
    }

    public function findByEmail(string $email): ?Employee
    {
        return $this->model->where('email', $email)->first();
    }

    public function create(array $data): Employee
    {
        return $this->model->create($data);
    }

    public function update(string $id, array $data): ?Employee
    {
        $employee = $this->findById($id);
        if (!$employee) {
            return null;
        }

        $employee->update($data);
        return $employee->fresh();
    }

    public function getAllWithFilters(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->query()
            ->with(['fileUploads', 'approvedBy']);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['stage'])) {
            $query->where('stage', $filters['stage']);
        }

        if (isset($filters['department'])) {
            $query->where('department', $filters['department']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        $query->orderBy($sortBy, $sortDirection);

        return $query->paginate($perPage);
    }

    public function getByStatus(string $status): Collection
    {
        return $this->model->where('status', $status)->get();
    }

    public function getByStage(string $stage): Collection
    {
        return $this->model->where('stage', $stage)->get();
    }

    public function getPendingApproval(): Collection
    {
        return $this->model->pendingApproval()
            ->with(['fileUploads', 'approvedBy'])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function approve(string $id, string $approvedBy): ?Employee
    {
        $employee = $this->findById($id);
        if (!$employee) {
            return null;
        }

        $employee->update([
            'status' => Employee::STATUS_APPROVED,
            'stage' => Employee::STAGE_ACTIVE,
            'approved_at' => now(),
            'approved_by' => $approvedBy,
            'rejection_reason' => null
        ]);

        return $employee->fresh();
    }

    public function reject(string $id, string $rejectionReason, string $rejectedBy): ?Employee
    {
        $employee = $this->findById($id);
        if (!$employee) {
            return null;
        }

        $employee->update([
            'status' => Employee::STATUS_REJECTED,
            'rejection_reason' => $rejectionReason,
            'approved_by' => $rejectedBy,
            'approved_at' => now()
        ]);

        return $employee->fresh();
    }

    public function updateStage(string $id, string $stage): ?Employee
    {
        $employee = $this->findById($id);
        if (!$employee || $employee->status !== Employee::STATUS_PENDING_APPROVAL) {
            return null;
        }

        $employee->update(['stage' => $stage]);
        return $employee->fresh();
    }

    public function saveQuestionnaireResponses(string $id, array $responses): ?Employee
    {
        $employee = $this->findById($id);
        if (!$employee) {
            return null;
        }

        // Prepare update data
        $updateData = [
            'questionnaire_responses' => $responses,
            'stage' => Employee::STAGE_QUESTIONNAIRE_COMPLETED
        ];

        // If this is the first time submitting, record when questionnaire was first completed
        if (!$employee->questionnaire_responses) {
            $updateData['profile_data'] = array_merge(
                $employee->profile_data ?? [],
                ['questionnaire_completed_at' => now()->toISOString()]
            );
        } else {
            // If updating, record the update timestamp
            $updateData['profile_data'] = array_merge(
                $employee->profile_data ?? [],
                ['questionnaire_updated_at' => now()->toISOString()]
            );
        }

        $employee->update($updateData);

        return $employee->fresh();
    }

    public function updateLocation(string $id, string $location): ?Employee
    {
        $employee = $this->findById($id);
        if (!$employee) {
            return null;
        }

        $employee->update([
            'location' => $location,
            'stage' => Employee::STAGE_LOCATION_SELECTED
        ]);

        return $employee->fresh();
    }

    public function updatePersonalInfo(string $id, array $personalInfo): ?Employee
    {
        $employee = $this->findById($id);
        if (!$employee) {
            return null;
        }

        // Merge new personal info with existing profile data
        $profileData = array_merge(
            $employee->profile_data ?? [],
            ['personal_info' => $personalInfo, 'personal_info_updated_at' => now()->toISOString()]
        );

        // Update basic fields if provided
        $updateData = ['profile_data' => $profileData];
        
        if (isset($personalInfo['first_name'])) {
            $updateData['first_name'] = $personalInfo['first_name'];
        }
        if (isset($personalInfo['last_name'])) {
            $updateData['last_name'] = $personalInfo['last_name'];
        }
        if (isset($personalInfo['phone'])) {
            $updateData['phone'] = $personalInfo['phone'];
        }

        $employee->update($updateData);
        return $employee->fresh();
    }

    public function getStatistics(): array
    {
        $total = $this->model->count();
        $pending = $this->model->pendingApproval()->count();
        $approved = $this->model->approved()->count();
        $rejected = $this->model->where('status', Employee::STATUS_REJECTED)->count();
        $paused = $this->model->where('status', Employee::STATUS_PAUSED)->count();
        $inactive = $this->model->where('status', Employee::STATUS_INACTIVE)->count();

        return [
            'total' => $total,
            'pending_approval' => $pending,
            'approved' => $approved,
            'rejected' => $rejected,
            'paused' => $paused,
            'inactive' => $inactive,
            'stages' => [
                'interview' => $this->model->byStage(Employee::STAGE_INTERVIEW)->count(),
                'location_selected' => $this->model->byStage(Employee::STAGE_LOCATION_SELECTED)->count(),
                'questionnaire_completed' => $this->model->byStage(Employee::STAGE_QUESTIONNAIRE_COMPLETED)->count(),
                'active' => $this->model->byStage(Employee::STAGE_ACTIVE)->count(),
            ]
        ];
    }

    // Lifecycle operations
    public function pause(string $id, ?string $reason = null): ?Employee
    {
        $employee = $this->findById($id);
        if (!$employee) { 
            return null; 
        }
        
        $profile = $employee->profile_data ?? [];
        if (!isset($profile['lifecycle'])) {
            $profile['lifecycle'] = [];
        }
        $profile['lifecycle'][] = ['action' => 'pause', 'reason' => $reason, 'at' => now()->toISOString()];
        
        $employee->update([
            'status' => Employee::STATUS_PAUSED,
            'profile_data' => $profile
        ]);
        return $employee->fresh();
    }

    public function resume(string $id): ?Employee
    {
        $employee = $this->findById($id);
        if (!$employee) { 
            return null; 
        }
        
        $profile = $employee->profile_data ?? [];
        if (!isset($profile['lifecycle'])) {
            $profile['lifecycle'] = [];
        }
        $profile['lifecycle'][] = ['action' => 'resume', 'at' => now()->toISOString()];
        
        $employee->update([
            'status' => Employee::STATUS_APPROVED,
            'profile_data' => $profile
        ]);
        return $employee->fresh();
    }

    public function deactivate(string $id, ?string $reason = null): ?Employee
    {
        $employee = $this->findById($id);
        if (!$employee) { 
            return null; 
        }
        
        $profile = $employee->profile_data ?? [];
        if (!isset($profile['lifecycle'])) {
            $profile['lifecycle'] = [];
        }
        $profile['lifecycle'][] = ['action' => 'deactivate', 'reason' => $reason, 'at' => now()->toISOString()];
        
        $employee->update([
            'status' => Employee::STATUS_INACTIVE,
            'profile_data' => $profile
        ]);
        return $employee->fresh();
    }

    public function activate(string $id): ?Employee
    {
        $employee = $this->findById($id);
        if (!$employee) { 
            return null; 
        }
        
        $profile = $employee->profile_data ?? [];
        if (!isset($profile['lifecycle'])) {
            $profile['lifecycle'] = [];
        }
        $profile['lifecycle'][] = ['action' => 'activate', 'at' => now()->toISOString()];
        
        $employee->update([
            'status' => Employee::STATUS_APPROVED,
            'stage' => Employee::STAGE_ACTIVE,
            'profile_data' => $profile
        ]);
        return $employee->fresh();
    }
}
