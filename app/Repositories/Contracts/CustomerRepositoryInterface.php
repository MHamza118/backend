<?php

namespace App\Repositories\Contracts;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface CustomerRepositoryInterface
{
    /**
     * Get all customers with optional filtering and pagination
     */
    public function getAllWithFilters(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Find customer by ID
     */
    public function findById(string $id): ?Customer;

    /**
     * Find customer by email
     */
    public function findByEmail(string $email): ?Customer;

    /**
     * Create a new customer
     */
    public function create(array $data): Customer;

    /**
     * Update customer data
     */
    public function update(string $id, array $data): ?Customer;

    /**
     * Delete customer (soft delete)
     */
    public function delete(string $id): bool;

    /**
     * Get customer dashboard data
     */
    public function getDashboardData(string $customerId): array;

    /**
     * Get customer profile with related data
     */
    public function getProfileWithRelations(string $customerId): ?Customer;

    /**
     * Update customer's loyalty points
     */
    public function updateLoyaltyPoints(string $customerId, int $points): bool;

    /**
     * Get customers by loyalty tier
     */
    public function getByLoyaltyTier(string $tier): Collection;

    /**
     * Get customers by location
     */
    public function getByLocation(string $location): Collection;

    /**
     * Update customer's last visit
     */
    public function updateLastVisit(string $customerId): bool;

    /**
     * Get customer statistics
     */
    public function getStatistics(): array;
}
