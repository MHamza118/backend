<?php

namespace App\Repositories\Contracts;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface EmployeeRepositoryInterface
{
    /**
     * Find employee by ID
     */
    public function findById(string $id): ?Employee;

    /**
     * Find employee by email
     */
    public function findByEmail(string $email): ?Employee;

    /**
     * Create new employee
     */
    public function create(array $data): Employee;

    /**
     * Update employee
     */
    public function update(string $id, array $data): ?Employee;

    /**
     * Get all employees with filters
     */
    public function getAllWithFilters(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get employees by status
     */
    public function getByStatus(string $status): Collection;

    /**
     * Get employees by stage
     */
    public function getByStage(string $stage): Collection;

    /**
     * Get pending approval employees
     */
    public function getPendingApproval(): Collection;

    /**
     * Approve employee
     */
    public function approve(string $id, string $approvedBy): ?Employee;

    /**
     * Reject employee
     */
    public function reject(string $id, string $rejectionReason, string $rejectedBy): ?Employee;

    /**
     * Update employee stage
     */
    public function updateStage(string $id, string $stage): ?Employee;

    /**
     * Save questionnaire responses
     */
    public function saveQuestionnaireResponses(string $id, array $responses): ?Employee;

    /**
     * Update location
     */
    public function updateLocation(string $id, string $location): ?Employee;

    /**
     * Update employee personal information
     */
    public function updatePersonalInfo(string $id, array $personalInfo): ?Employee;

    /**
     * Get employee statistics
     */
    public function getStatistics(): array;

    // Lifecycle operations
    public function pause(string $id, ?string $reason = null): ?Employee;
    public function resume(string $id): ?Employee;
    public function deactivate(string $id, ?string $reason = null): ?Employee;
    public function activate(string $id): ?Employee;
}
