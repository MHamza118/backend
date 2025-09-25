<?php

namespace App\Repositories;

use App\Models\Customer;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

class CustomerRepository implements CustomerRepositoryInterface
{
    protected $model;

    public function __construct(Customer $model)
    {
        $this->model = $model;
    }

    /**
     * Get all customers with optional filtering and pagination
     */
    public function getAllWithFilters(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->query()->with(['locations']);

        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['loyalty_tier'])) {
            $query->byLoyaltyTier($filters['loyalty_tier']);
        }

        if (isset($filters['location'])) {
            $query->where('home_location', $filters['location']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('email', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('phone', 'like', '%' . $filters['search'] . '%');
            });
        }

        // Sort by creation date (newest first) by default
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        $query->orderBy($sortBy, $sortDirection);

        return $query->paginate($perPage);
    }

    /**
     * Find customer by ID
     */
    public function findById(string $id): ?Customer
    {
        return $this->model->find($id);
    }

    /**
     * Find customer by email
     */
    public function findByEmail(string $email): ?Customer
    {
        return $this->model->where('email', $email)->first();
    }

    /**
     * Create a new customer
     */
    public function create(array $data): Customer
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $customer = $this->model->create($data);

        // Create customer locations if provided
        if (isset($data['locations']) && is_array($data['locations'])) {
            foreach ($data['locations'] as $locationData) {
                $customer->locations()->create([
                    'name' => $locationData['name'],
                    'is_home' => $locationData['is_home'] ?? false,
                    'is_active' => true
                ]);
            }
        }

        return $customer->load(['locations']);
    }

    /**
     * Update customer data
     */
    public function update(string $id, array $data): ?Customer
    {
        $customer = $this->findById($id);
        if (!$customer) {
            return null;
        }

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $customer->update($data);

        // Update locations if provided
        if (isset($data['locations']) && is_array($data['locations'])) {
            // Delete existing locations
            $customer->locations()->delete();
            
            // Create new locations
            foreach ($data['locations'] as $locationData) {
                $customer->locations()->create([
                    'name' => $locationData['name'],
                    'is_home' => $locationData['is_home'] ?? false,
                    'is_active' => true
                ]);
            }
        }

        return $customer->fresh(['locations']);
    }

    /**
     * Delete customer (soft delete)
     */
    public function delete(string $id): bool
    {
        $customer = $this->findById($id);
        return $customer ? $customer->delete() : false;
    }

    /**
     * Get customer dashboard data
     */
    public function getDashboardData(string $customerId): array
    {
        $customer = $this->model->with([
            'locations'
            // 'favoriteItems', // Temporarily disabled - table not created yet
            // 'orders' => function ($query) { // Temporarily disabled - table not created yet
            //     $query->latest()->limit(3);
            // }
        ])->find($customerId);

        if (!$customer) {
            return [];
        }

        return [
            'customer' => $customer,
            'loyalty_tier' => $customer->loyalty_tier,
            'recent_orders' => [], // Temporarily empty - orders table not created yet
            'favorite_items' => [], // Temporarily empty - favorite items table not created yet
            'available_rewards' => $this->getAvailableRewards($customer),
            'announcements' => [], // Will be populated by service layer
            'event_notifications' => [], // Will be populated by service layer
        ];
    }

    /**
     * Get customer profile with related data
     */
    public function getProfileWithRelations(string $customerId): ?Customer
    {
        return $this->model->with([
            'locations'
            // 'favoriteItems', // Temporarily disabled - table not created yet
            // 'orders', // Temporarily disabled - table not created yet  
            // 'rewards', // Temporarily disabled - table not created yet
            // 'dismissedAnnouncements' // Temporarily disabled - table not created yet
        ])->find($customerId);
    }

    /**
     * Update customer's loyalty points
     */
    public function updateLoyaltyPoints(string $customerId, int $points): bool
    {
        $customer = $this->findById($customerId);
        if (!$customer) {
            return false;
        }

        $customer->increment('loyalty_points', $points);
        return true;
    }

    /**
     * Get customers by loyalty tier
     */
    public function getByLoyaltyTier(string $tier): Collection
    {
        return $this->model->active()->byLoyaltyTier($tier)->get();
    }

    /**
     * Get customers by location
     */
    public function getByLocation(string $location): Collection
    {
        return $this->model->active()->where('home_location', $location)->get();
    }

    /**
     * Update customer's last visit
     */
    public function updateLastVisit(string $customerId): bool
    {
        $customer = $this->findById($customerId);
        if (!$customer) {
            return false;
        }

        $customer->update(['last_visit' => now()]);
        return true;
    }

    /**
     * Get customer statistics
     */
    public function getStatistics(): array
    {
        $totalCustomers = $this->model->count();
        $activeCustomers = $this->model->active()->count();

        return [
            'total_customers' => $totalCustomers,
            'active_customers' => $activeCustomers,
            'inactive_customers' => $totalCustomers - $activeCustomers,
            'by_loyalty_tier' => [
                'bronze' => $this->model->active()->byLoyaltyTier('bronze')->count(),
                'silver' => $this->model->active()->byLoyaltyTier('silver')->count(),
                'gold' => $this->model->active()->byLoyaltyTier('gold')->count(),
                'platinum' => $this->model->active()->byLoyaltyTier('platinum')->count(),
            ],
            'total_loyalty_points' => $this->model->active()->sum('loyalty_points'),
            'total_orders' => $this->model->active()->sum('total_orders'),
            'total_spent' => $this->model->active()->sum('total_spent'),
            'new_this_month' => $this->model->where('created_at', '>=', now()->startOfMonth())->count(),
        ];
    }

    /**
     * Get available rewards for customer
     */
    protected function getAvailableRewards(Customer $customer): array
    {
        $rewards = [
            [
                'id' => 'free_appetizer',
                'name' => 'Free Appetizer',
                'points_required' => 500,
                'available' => $customer->loyalty_points >= 500,
                'description' => 'Enjoy a complimentary appetizer',
                'type' => 'food'
            ],
            [
                'id' => 'discount_10_percent',
                'name' => '10% Off Next Order',
                'points_required' => 750,
                'available' => $customer->loyalty_points >= 750,
                'description' => 'Get 10% off your next order',
                'type' => 'discount'
            ],
            [
                'id' => 'free_dessert',
                'name' => 'Free Dessert',
                'points_required' => 1000,
                'available' => $customer->loyalty_points >= 1000,
                'description' => 'Choose any dessert on the house',
                'type' => 'food'
            ]
        ];

        return $rewards;
    }
}
