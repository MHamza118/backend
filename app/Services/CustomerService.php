<?php

namespace App\Services;

use App\Models\Customer;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Repositories\Contracts\AnnouncementRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    protected $customerRepository;
    protected $announcementRepository;

    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        AnnouncementRepositoryInterface $announcementRepository
    ) {
        $this->customerRepository = $customerRepository;
        $this->announcementRepository = $announcementRepository;
    }

    /**
     * Get all customers with filtering and pagination
     */
    public function getAllCustomers(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->customerRepository->getAllWithFilters($filters, $perPage);
    }

    /**
     * Get customer by ID
     */
    public function getCustomerById(string $id): ?Customer
    {
        return $this->customerRepository->findById($id);
    }

    /**
     * Register new customer
     */
    public function registerCustomer(array $data): Customer
    {
        // Validate unique email
        if ($this->customerRepository->findByEmail($data['email'])) {
            throw ValidationException::withMessages([
                'email' => ['A customer with this email already exists.']
            ]);
        }

        // Set default values
        $data['status'] = 'ACTIVE';
        $data['loyalty_points'] = 0;
        $data['total_orders'] = 0;
        $data['total_spent'] = 0.00;
        $data['preferences'] = [
            'notifications' => true,
            'marketing' => false
        ];
        $data['last_visit'] = now();

        // Parse name into first and last
        $nameParts = explode(' ', $data['name']);
        $data['first_name'] = $nameParts[0] ?? '';
        $data['last_name'] = implode(' ', array_slice($nameParts, 1)) ?? '';

        // Set home location from locations array
        if (isset($data['locations']) && is_array($data['locations'])) {
            $homeLocation = collect($data['locations'])->firstWhere('is_home', true);
            $data['home_location'] = $homeLocation['name'] ?? '';
        }

        return $this->customerRepository->create($data);
    }

    /**
     * Update customer profile
     */
    public function updateCustomerProfile(string $id, array $data): ?Customer
    {
        $customer = $this->customerRepository->findById($id);
        if (!$customer) {
            return null;
        }

        // Parse name into first and last if name is updated
        if (isset($data['name'])) {
            $nameParts = explode(' ', $data['name']);
            $data['first_name'] = $nameParts[0] ?? '';
            $data['last_name'] = implode(' ', array_slice($nameParts, 1)) ?? '';
        }

        // Set home location from locations array if provided
        if (isset($data['locations']) && is_array($data['locations'])) {
            $homeLocation = collect($data['locations'])->firstWhere('is_home', true);
            $data['home_location'] = $homeLocation['name'] ?? $customer->home_location;
        }

        return $this->customerRepository->update($id, $data);
    }

    /**
     * Get customer dashboard data
     */
    public function getCustomerDashboard(string $customerId): array
    {
        // Update last visit
        $this->customerRepository->updateLastVisit($customerId);

        $dashboardData = $this->customerRepository->getDashboardData($customerId);
        
        if (empty($dashboardData)) {
            return [];
        }

        // Get customer-specific announcements
        $customer = $dashboardData['customer'];
        $announcements = $this->getCustomerAnnouncements($customer);
        $dashboardData['announcements'] = $announcements['all'] ?? [];
        $dashboardData['event_notifications'] = $announcements['events'] ?? [];

        return $dashboardData;
    }

    /**
     * Get customer announcements
     */
    public function getCustomerAnnouncements(Customer $customer): array
    {
        $allAnnouncements = $this->announcementRepository->getActiveAnnouncementsForCustomer($customer);
        
        // Separate events from other announcements
        $events = $allAnnouncements->where('type', 'event');
        $others = $allAnnouncements->where('type', '!=', 'event');

        return [
            'all' => $allAnnouncements->values(), // Reset keys
            'events' => $events->values(), // Reset keys
            'others' => $others->values() // Reset keys
        ];
    }

    /**
     * Dismiss announcement for customer
     */
    public function dismissAnnouncement(string $customerId, string $announcementId): bool
    {
        $customer = $this->customerRepository->findById($customerId);
        if (!$customer) {
            return false;
        }

        // Check if announcement exists and is dismissible
        $announcement = $this->announcementRepository->findById($announcementId);
        if (!$announcement || !$announcement->is_dismissible) {
            return false;
        }

        // Add to dismissed announcements
        $customer->dismissedAnnouncements()->syncWithoutDetaching([$announcementId]);

        return true;
    }

    /**
     * Award loyalty points to customer
     */
    public function awardLoyaltyPoints(string $customerId, int $points, string $reason = ''): bool
    {
        $customer = $this->customerRepository->findById($customerId);
        if (!$customer) {
            return false;
        }

        $this->customerRepository->updateLoyaltyPoints($customerId, $points);

        // Log the loyalty points transaction (if you have a loyalty points history table)
        // $this->loyaltyService->logTransaction($customerId, $points, $reason);

        return true;
    }

    /**
     * Redeem reward for customer
     */
    public function redeemReward(string $customerId, string $rewardId): array
    {
        $customer = $this->customerRepository->findById($customerId);
        if (!$customer) {
            return ['success' => false, 'message' => 'Customer not found'];
        }

        $dashboardData = $this->customerRepository->getDashboardData($customerId);
        $availableRewards = $dashboardData['available_rewards'] ?? [];
        
        $reward = collect($availableRewards)->firstWhere('id', $rewardId);
        if (!$reward) {
            return ['success' => false, 'message' => 'Reward not found'];
        }

        if (!$reward['available']) {
            return ['success' => false, 'message' => 'Insufficient loyalty points'];
        }

        // Deduct points
        $this->customerRepository->updateLoyaltyPoints($customerId, -$reward['points_required']);

        // Create reward record (if you have a rewards table)
        // $this->createRewardRecord($customerId, $rewardId, $reward['points_required']);

        return [
            'success' => true, 
            'message' => 'Reward redeemed successfully',
            'reward' => $reward,
            'remaining_points' => $customer->loyalty_points - $reward['points_required']
        ];
    }

    /**
     * Get customer statistics
     */
    public function getCustomerStatistics(): array
    {
        return $this->customerRepository->getStatistics();
    }

    /**
     * Search customers
     */
    public function searchCustomers(string $query, int $perPage = 15): LengthAwarePaginator
    {
        return $this->customerRepository->getAllWithFilters([
            'search' => $query
        ], $perPage);
    }

    /**
     * Get customers by loyalty tier
     */
    public function getCustomersByLoyaltyTier(string $tier): Collection
    {
        return $this->customerRepository->getByLoyaltyTier($tier);
    }

    /**
     * Get customers by location
     */
    public function getCustomersByLocation(string $location): Collection
    {
        return $this->customerRepository->getByLocation($location);
    }

    /**
     * Soft delete customer
     */
    public function deleteCustomer(string $id): bool
    {
        return $this->customerRepository->delete($id);
    }

    /**
     * Get customer profile with all relations
     */
    public function getCustomerProfile(string $customerId): ?Customer
    {
        return $this->customerRepository->getProfileWithRelations($customerId);
    }

    /**
     * Update customer preferences
     */
    public function updateCustomerPreferences(string $customerId, array $preferences): ?Customer
    {
        $customer = $this->customerRepository->findById($customerId);
        if (!$customer) {
            return null;
        }

        $currentPreferences = $customer->preferences ?? [];
        $updatedPreferences = array_merge($currentPreferences, $preferences);

        return $this->customerRepository->update($customerId, [
            'preferences' => $updatedPreferences
        ]);
    }

    /**
     * Process customer order (update totals and points)
     */
    public function processCustomerOrder(string $customerId, float $orderAmount, int $itemCount): bool
    {
        $customer = $this->customerRepository->findById($customerId);
        if (!$customer) {
            return false;
        }

        // Calculate loyalty points (1 point per dollar spent, with tier multiplier)
        $pointsMultiplier = $this->getLoyaltyPointsMultiplier($customer->loyalty_tier);
        $earnedPoints = floor($orderAmount * $pointsMultiplier);

        // Update customer totals
        $customer->increment('total_orders', 1);
        $customer->increment('total_spent', $orderAmount);
        $customer->increment('loyalty_points', $earnedPoints);
        $customer->update(['last_visit' => now()]);

        return true;
    }

    /**
     * Get loyalty points multiplier based on tier
     */
    protected function getLoyaltyPointsMultiplier(string $tier): float
    {
        switch (strtolower($tier)) {
            case 'platinum':
                return 2.5;
            case 'gold':
                return 2.0;
            case 'silver':
                return 1.5;
            case 'bronze':
            default:
                return 1.0;
        }
    }
}
