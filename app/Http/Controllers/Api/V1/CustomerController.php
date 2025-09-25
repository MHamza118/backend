<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerRegistrationRequest;
use App\Http\Requests\UpdateCustomerProfileRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\CustomerDashboardResource;
use App\Http\Resources\AnnouncementResource;
use App\Services\CustomerService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Models\Customer;

class CustomerController extends Controller
{
    protected $customerService;

    public function __construct(CustomerService $customerService)
    {
        $this->customerService = $customerService;
    }

    /**
     * Display a listing of customers (Admin only)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['status', 'loyalty_tier', 'location', 'search', 'sort_by', 'sort_direction']);
            $perPage = $request->get('per_page', 15);

            // Get customers with eager loaded relationships
            $customers = Customer::with(['locations'])
                ->when($filters['status'] ?? null, function ($query, $status) {
                    if ($status !== 'All') {
                        $query->where('status', $status);
                    }
                })
                ->when($filters['search'] ?? null, function ($query, $search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'LIKE', "%{$search}%")
                          ->orWhere('email', 'LIKE', "%{$search}%")
                          ->orWhere('phone', 'LIKE', "%{$search}%");
                    });
                })
                ->orderBy($filters['sort_by'] ?? 'created_at', $filters['sort_direction'] ?? 'desc')
                ->paginate($perPage);

            // Transform customers to include proper location data
            $customersWithLocations = $customers->getCollection()->map(function ($customer) {
                // Get location IDs from customer_locations relationship
                $locationIds = $customer->locations->pluck('id')->toArray();
                
                // Find home location ID from customer_locations where is_home = true
                $homeLocationId = $customer->locations->where('is_home', true)->first()?->id;
                
                // Add the computed fields to customer data
                $customer->location_ids = $locationIds;
                $customer->home_location_id = $homeLocationId;
                
                return $customer;
            });
            
            $customers->setCollection($customersWithLocations);

            return response()->json([
                'status' => 'success',
                'message' => 'Customers retrieved successfully',
                'data' => CustomerResource::collection($customers),
                'meta' => [
                    'pagination' => [
                        'current_page' => $customers->currentPage(),
                        'last_page' => $customers->lastPage(),
                        'per_page' => $customers->perPage(),
                        'total' => $customers->total(),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Customer retrieval failed: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve customers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Register a new customer
     */
    public function register(CustomerRegistrationRequest $request): JsonResponse
    {
        try {
            $customer = $this->customerService->registerCustomer($request->validated());

            // Create authentication token
            $token = $customer->createToken('customer-token')->plainTextToken;

            return response()->json([
                'status' => 'success',
                'message' => 'Customer registered successfully',
                'data' => [
                    'customer' => new CustomerResource($customer->load('locations')),
                    'token' => $token,
                    'token_type' => 'Bearer'
                ]
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the authenticated customer's profile
     */
    public function profile(Request $request): JsonResponse
    {
        try {
            $customer = $request->user();
            $profile = $this->customerService->getCustomerProfile($customer->id);

            if (!$profile) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Customer profile not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Profile retrieved successfully',
                'data' => new CustomerResource($profile)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the authenticated customer's profile
     */
    public function updateProfile(UpdateCustomerProfileRequest $request): JsonResponse
    {
        try {
            $customer = $request->user();
            $updatedCustomer = $this->customerService->updateCustomerProfile(
                $customer->id,
                $request->validated()
            );

            if (!$updatedCustomer) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Customer not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Profile updated successfully',
                'data' => new CustomerResource($updatedCustomer)
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get the authenticated customer's dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        try {
            $customer = $request->user();
            $dashboardData = $this->customerService->getCustomerDashboard($customer->id);

            if (empty($dashboardData)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Customer dashboard data not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Dashboard data retrieved successfully',
                'data' => new CustomerDashboardResource($dashboardData)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve dashboard data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get customer announcements
     */
    public function announcements(Request $request): JsonResponse
    {
        try {
            $customer = $request->user();
            $announcements = $this->customerService->getCustomerAnnouncements($customer);

            return response()->json([
                'status' => 'success',
                'message' => 'Announcements retrieved successfully',
                'data' => [
                    'all' => AnnouncementResource::collection($announcements['all']),
                    'events' => AnnouncementResource::collection($announcements['events']),
                    'others' => AnnouncementResource::collection($announcements['others'])
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve announcements',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Dismiss an announcement
     */
    public function dismissAnnouncement(Request $request, string $announcementId): JsonResponse
    {
        try {
            $customer = $request->user();
            $success = $this->customerService->dismissAnnouncement($customer->id, $announcementId);

            if (!$success) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to dismiss announcement'
                ], 400);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Announcement dismissed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to dismiss announcement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Redeem a reward
     */
    public function redeemReward(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'reward_id' => 'required|string'
            ]);

            $customer = $request->user();
            $result = $this->customerService->redeemReward($customer->id, $request->reward_id);

            if (!$result['success']) {
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message']
                ], 400);
            }

            return response()->json([
                'status' => 'success',
                'message' => $result['message'],
                'data' => [
                    'reward' => $result['reward'],
                    'remaining_points' => $result['remaining_points']
                ]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to redeem reward',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update customer preferences
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'preferences' => 'required|array',
                'preferences.notifications' => 'sometimes|boolean',
                'preferences.marketing' => 'sometimes|boolean'
            ]);

            $customer = $request->user();
            $updatedCustomer = $this->customerService->updateCustomerPreferences(
                $customer->id,
                $request->preferences
            );

            if (!$updatedCustomer) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Customer not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Preferences updated successfully',
                'data' => [
                    'preferences' => $updatedCustomer->preferences
                ]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update preferences',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show a specific customer (Admin only)
     */
    public function show(string $id): JsonResponse
    {
        try {
            $customer = Customer::with(['locations'])->find($id);

            if (!$customer) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Customer not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Customer retrieved successfully',
                'data' => new CustomerResource($customer)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve customer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a customer (Admin only)
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $success = $this->customerService->deleteCustomer($id);

            if (!$success) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Customer not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Customer deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete customer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get customer statistics (Admin only)
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = $this->customerService->getCustomerStatistics();

            return response()->json([
                'status' => 'success',
                'message' => 'Statistics retrieved successfully',
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
