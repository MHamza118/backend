<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminResource;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Admin;
use App\Models\Employee;
use App\Models\Customer;
use App\Models\Location;
use App\Models\OnboardingPage;
use App\Models\EmployeeOnboardingProgress;
use App\Models\TrainingAssignment;
use App\Models\TrainingModule;
use App\Services\AdminService;
use App\Http\Requests\CreateAdminRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    use ApiResponseTrait;

    protected $adminService;

    public function __construct(AdminService $adminService)
    {
        $this->adminService = $adminService;
    }

    /**
     * Admin login
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        try {
            $loginData = $this->adminService->login(
                $request->email,
                $request->password
            );

            $response = [
                'user_type' => 'admin',
                'admin' => new AdminResource($loginData['admin']),
                'token' => $loginData['token'],
                'role' => $loginData['role'],
                'status' => $loginData['status'],
                'can_access_dashboard' => $loginData['can_access_dashboard']
            ];

            return $this->successResponse($response, "Admin login successful. Welcome {$loginData['admin']->full_name}!");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), 'Login failed');
        } catch (\Exception $e) {
            return $this->errorResponse('Login failed: ' . $e->getMessage());
        }
    }

    /**
     * Get admin dashboard data
     */
    public function dashboard(Request $request): JsonResponse
    {
        try {
            $admin = $request->user();

            // Update last login time
            $admin->update([
                'last_login_at' => now()
            ]);

            // Get dashboard statistics
            $stats = [
                'total_employees' => Employee::count(),
                'pending_employees' => Employee::pendingApproval()->count(),
                'approved_employees' => Employee::approved()->count(),
                'total_customers' => Customer::count(),
                'new_customers_today' => Customer::whereDate('created_at', today())->count(),
                'employees_by_stage' => [
                    'interview' => Employee::byStage(Employee::STAGE_INTERVIEW)->count(),
                    'location_selected' => Employee::byStage(Employee::STAGE_LOCATION_SELECTED)->count(),
                    'questionnaire_completed' => Employee::byStage(Employee::STAGE_QUESTIONNAIRE_COMPLETED)->count(),
                    'active' => Employee::byStage(Employee::STAGE_ACTIVE)->count(),
                ],
                'employees_by_status' => [
                    'pending_approval' => Employee::where('status', Employee::STATUS_PENDING_APPROVAL)->count(),
                    'approved' => Employee::where('status', Employee::STATUS_APPROVED)->count(),
                    'rejected' => Employee::where('status', Employee::STATUS_REJECTED)->count(),
                ]
            ];

            // Get training statistics
            $totalAssignments = TrainingAssignment::count();
            $completedAssignments = TrainingAssignment::where('status', 'completed')->count();
            $pendingAssignments = TrainingAssignment::where('status', 'assigned')->count();
            $inProgressAssignments = TrainingAssignment::where('status', 'in_progress')->count();
            $overdueAssignments = TrainingAssignment::where('status', 'overdue')->count();
            
            $trainingStats = [
                'total_assignments' => $totalAssignments,
                'completed_assignments' => $completedAssignments,
                'pending_assignments' => $pendingAssignments,
                'in_progress_assignments' => $inProgressAssignments,
                'overdue_assignments' => $overdueAssignments,
                'completion_rate' => $totalAssignments > 0 ? round(($completedAssignments / $totalAssignments) * 100, 1) : 0
            ];
            
            // Add training stats to main stats array
            $stats['training'] = $trainingStats;

            // Get recent activities (last 10 employees registered)
            $recentEmployees = Employee::with(['approvedBy'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($employee) {
                    return [
                        'id' => $employee->id,
                        'name' => $employee->full_name,
                        'email' => $employee->email,
                        'stage' => $employee->stage,
                        'status' => $employee->status,
                        'created_at' => $employee->created_at->toISOString(),
                        'approved_at' => $employee->approved_at?->toISOString(),
                        'approved_by' => $employee->approvedBy ? $employee->approvedBy->name : null,
                    ];
                });

            // Get recent customers (last 10 customers registered)
            $recentCustomers = Customer::orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($customer) {
                    return [
                        'id' => $customer->id,
                        'name' => $customer->name,
                        'email' => $customer->email,
                        'phone' => $customer->phone,
                        'created_at' => $customer->created_at->toISOString(),
                    ];
                });

            return $this->successResponse([
                'admin' => new AdminResource($admin),
                'dashboard_data' => [
                    'welcome_message' => "Welcome back, {$admin->full_name}!",
                    'role' => $admin->role,
                    'status' => $admin->status,
                    'last_login_at' => $admin->last_login_at?->toISOString(),
                    'access_level' => 'admin_dashboard',
                    'statistics' => $stats,
                    'recent_activities' => [
                        'employees' => $recentEmployees,
                        'customers' => $recentCustomers,
                    ],
                    'quick_actions' => [
                        'pending_employees_count' => $stats['pending_employees'],
                        'can_approve_employees' => true,
                        'can_manage_customers' => true,
                        'can_view_analytics' => true,
                    ]
                ]
            ], 'Admin dashboard data retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get dashboard data: ' . $e->getMessage());
        }
    }

    /**
     * Get admin profile
     */
    public function profile(Request $request): JsonResponse
    {
        try {
            $admin = $request->user();
            
            return $this->successResponse(
                new AdminResource($admin),
                'Admin profile retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve profile: ' . $e->getMessage());
        }
    }

    /**
     * Logout admin
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return $this->successResponse(null, 'Admin logout successful');
        } catch (\Exception $e) {
            return $this->errorResponse('Logout failed: ' . $e->getMessage());
        }
    }

    /**
     * Get all admin users (role-based access)
     */
    public function getAdminUsers(Request $request): JsonResponse
    {
        try {
            $admin = $request->user();
            $filters = $request->only(['role', 'status']);
            
            $result = $this->adminService->getAdminUsers($admin, $filters);
            
            return $this->successResponse([
                'users' => AdminResource::collection($result['admins']),
                'meta' => [
                    'total' => $result['total'],
                    'can_create' => $result['can_create'],
                    'requesting_user_role' => $result['requesting_user_role']
                ]
            ], 'Admin users retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get admin users: ' . $e->getMessage());
        }
    }

    /**
     * Create new admin/manager user (OWNER only)
     */
    public function createAdminUser(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|email|unique:admins,email',
                'password' => 'required|string|min:8|confirmed',
                'phone' => 'nullable|string',
                'role' => 'required|in:owner,admin,manager,hiring_manager,expo',
                'location_id' => 'nullable|exists:locations,id',
                'department' => 'nullable|string',
                'notes' => 'nullable|string'
            ]);

            $admin = $request->user();
            $result = $this->adminService->createAdminUser($admin, $validatedData);
            
            return $this->successResponse([
                'user' => new AdminResource($result['admin']),
                'message' => $result['message']
            ], $result['message'], 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create admin user: ' . $e->getMessage());
        }
    }

    /**
     * Update admin user permissions
     */
    public function updateAdminPermissions(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'permissions' => 'required|array',
                'permissions.*' => 'string|in:' . implode(',', Admin::getAllPermissions())
            ]);

            $admin = $request->user();
            $result = $this->adminService->updateAdminPermissions(
                $admin, 
                (int)$id, 
                $request->permissions
            );
            
            return $this->successResponse([
                'user' => new AdminResource($result['admin']),
            ], $result['message']);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update permissions: ' . $e->getMessage());
        }
    }

    /**
     * Deactivate admin user
     */
    public function deactivateAdminUser(Request $request, $id): JsonResponse
    {
        try {
            $admin = $request->user();
            $result = $this->adminService->deactivateAdminUser($admin, (int)$id);
            
            return $this->successResponse([
                'user' => new AdminResource($result['admin']),
            ], $result['message']);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to deactivate user: ' . $e->getMessage());
        }
    }

    /**
     * Get available roles and permissions for management
     */
    public function getRolesAndPermissions(Request $request): JsonResponse
    {
        try {
            $admin = $request->user();
            $result = $this->adminService->getAvailableRolesAndPermissions($admin);
            
            return $this->successResponse($result, 'Roles and permissions retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get roles and permissions: ' . $e->getMessage());
        }
    }

    /**
     * Get all locations
     */
    public function getLocations(Request $request): JsonResponse
    {
        try {
            $locations = Location::active()->orderBy('name')->get();
            
            return $this->successResponse(
                $locations,
                'Locations retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get locations: ' . $e->getMessage());
        }
    }

    /**
     * Create new location
     */
    public function createLocation(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'address' => 'nullable|string|max:500',
                'city' => 'nullable|string|max:100',
                'state' => 'nullable|string|max:50',
                'zip_code' => 'nullable|string|max:20',
                'country' => 'nullable|string|max:50',
                'phone' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'description' => 'nullable|string|max:1000',
                'settings' => 'nullable|array'
            ]);

            $location = Location::create([
                'name' => $request->name,
                'address' => $request->address,
                'city' => $request->city,
                'state' => $request->state,
                'zip_code' => $request->zip_code,
                'country' => $request->country ?? 'USA',
                'phone' => $request->phone,
                'email' => $request->email,
                'description' => $request->description,
                'settings' => $request->settings ?? [],
                'created_by' => $request->user()->id,
                'active' => true
            ]);
            
            return $this->successResponse(
                $location,
                'Location created successfully',
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create location: ' . $e->getMessage());
        }
    }

    /**
     * Update location
     */
    public function updateLocation(Request $request, $id): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'address' => 'nullable|string|max:500',
                'city' => 'nullable|string|max:100',
                'state' => 'nullable|string|max:50',
                'zip_code' => 'nullable|string|max:20',
                'country' => 'nullable|string|max:50',
                'phone' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'description' => 'nullable|string|max:1000',
                'active' => 'boolean',
                'settings' => 'nullable|array'
            ]);

            $location = Location::findOrFail($id);
            $location->update($validatedData);
            
            return $this->successResponse(
                $location->fresh(),
                'Location updated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update location: ' . $e->getMessage());
        }
    }

    /**
     * Delete/deactivate location
     */
    public function deleteLocation(Request $request, $id): JsonResponse
    {
        try {
            $location = Location::findOrFail($id);
            $location->update(['active' => false]);
            
            return $this->successResponse(
                $location->fresh(),
                'Location deactivated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete location: ' . $e->getMessage());
        }
    }

    /**
     * Get all onboarding pages
     */
    public function getOnboardingPages(Request $request): JsonResponse
    {
        try {
            $pages = OnboardingPage::ordered()->get();
            
            return $this->successResponse(
                $pages,
                'Onboarding pages retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get onboarding pages: ' . $e->getMessage());
        }
    }

    /**
     * Create new onboarding page
     */
    public function createOnboardingPage(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'title' => 'required|string|max:255',
                'content' => 'required|string',
                'icon' => 'nullable|string|max:50',
                'order' => 'nullable|integer|min:1',
                'active' => 'nullable|boolean'
            ]);

            $page = OnboardingPage::create([
                'title' => $request->title,
                'content' => $request->content,
                'icon' => $request->icon ?? 'BookOpen',
                'order' => $request->order ?? (OnboardingPage::max('order') + 1),
                'active' => $request->active ?? true
            ]);
            
            return $this->successResponse(
                $page,
                'Onboarding page created successfully',
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create onboarding page: ' . $e->getMessage());
        }
    }

    /**
     * Update onboarding page
     */
    public function updateOnboardingPage(Request $request, $id): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'title' => 'required|string|max:255',
                'content' => 'required|string',
                'icon' => 'nullable|string|max:50',
                'order' => 'nullable|integer|min:1',
                'active' => 'nullable|boolean'
            ]);

            $page = OnboardingPage::findOrFail($id);
            $page->update($validatedData);
            
            return $this->successResponse(
                $page->fresh(),
                'Onboarding page updated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update onboarding page: ' . $e->getMessage());
        }
    }

    /**
     * Delete onboarding page
     */
    public function deleteOnboardingPage(Request $request, $id): JsonResponse
    {
        try {
            $page = OnboardingPage::findOrFail($id);
            
            // Check if any employees have progress on this page
            $hasProgress = EmployeeOnboardingProgress::where('onboarding_page_id', $id)->exists();
            
            if ($hasProgress) {
                // Soft delete by deactivating instead of hard delete
                $page->update(['active' => false]);
                $message = 'Onboarding page deactivated successfully (has employee progress data)';
            } else {
                // Safe to hard delete
                $page->delete();
                $message = 'Onboarding page deleted successfully';
            }
            
            return $this->successResponse(null, $message);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete onboarding page: ' . $e->getMessage());
        }
    }

    /**
     * Toggle onboarding page active status
     */
    public function toggleOnboardingPageStatus(Request $request, $id): JsonResponse
    {
        try {
            $page = OnboardingPage::findOrFail($id);
            $page->update(['active' => !$page->active]);
            
            $status = $page->active ? 'activated' : 'deactivated';
            
            return $this->successResponse(
                $page->fresh(),
                "Onboarding page {$status} successfully"
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to toggle page status: ' . $e->getMessage());
        }
    }

    /**
     * Create new customer (Admin only)
     */
    public function createCustomer(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|email|unique:customers,email',
                'phone' => 'nullable|string|regex:/^\+?[1-9]\d{1,14}$/|max:20',
                'password' => 'required|string|min:6',
                'home_location' => 'nullable|string|max:255',
                'homeLocation' => 'nullable|string|max:255', // Support frontend field name
                'status' => 'in:ACTIVE,INACTIVE,SUSPENDED',
                'loyalty_points' => 'integer|min:0'
            ]);

            // Map frontend field names to backend field names
            if (isset($validatedData['homeLocation'])) {
                $validatedData['home_location'] = $validatedData['homeLocation'];
            }

            $customer = Customer::create([
                'name' => $validatedData['name'],
                'first_name' => $validatedData['first_name'],
                'last_name' => $validatedData['last_name'],
                'email' => $validatedData['email'],
                'phone' => $validatedData['phone'] ?? null,
                'password' => bcrypt($validatedData['password']),
                'home_location' => $validatedData['home_location'] ?? null,
                'status' => $validatedData['status'] ?? 'ACTIVE',
                'loyalty_points' => $validatedData['loyalty_points'] ?? 0,
                'total_orders' => 0,
                'total_spent' => 0.00,
                'preferences' => []
            ]);

            return $this->successResponse(
                $customer,
                'Customer created successfully',
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create customer: ' . $e->getMessage());
        }
    }

    /**
     * Update customer (Admin only)
     */
    public function updateCustomer(Request $request, $id): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'first_name' => 'nullable|string|max:255',
                'last_name' => 'nullable|string|max:255',
                'email' => 'required|email|unique:customers,email,' . $id,
                'phone' => 'nullable|string|regex:/^\+?[1-9]\d{1,14}$/|max:20',
                'password' => 'nullable|string|min:6', // Optional for updates
                'home_location' => 'nullable|string|max:255',
                'homeLocation' => 'nullable|string|max:255', // Support frontend field name
                'status' => 'in:ACTIVE,INACTIVE,SUSPENDED',
                'loyalty_points' => 'nullable|integer|min:0',
                'loyaltyPoints' => 'nullable|integer|min:0', // Support frontend field name
                'locations' => 'nullable|array',
                'locations.*' => 'string'
            ]);

            // Map frontend field names to backend field names
            if (isset($validatedData['homeLocation'])) {
                $validatedData['home_location'] = $validatedData['homeLocation'];
                unset($validatedData['homeLocation']);
            }
            if (isset($validatedData['loyaltyPoints'])) {
                $validatedData['loyalty_points'] = $validatedData['loyaltyPoints'];
                unset($validatedData['loyaltyPoints']);
            }

            // Hash password if provided
            if (isset($validatedData['password'])) {
                $validatedData['password'] = bcrypt($validatedData['password']);
            }

            $customer = Customer::findOrFail($id);
            $customer->update($validatedData);

            return $this->successResponse(
                $customer->fresh(),
                'Customer updated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update customer: ' . $e->getMessage());
        }
    }

    /**
     * Get all customers with filters
     */
    public function getCustomers(Request $request): JsonResponse
    {
        try {
            $query = Customer::query();
            
            // Apply filters
            if ($request->has('status') && $request->status !== 'All') {
                $query->where('status', $request->status);
            }
            
            if ($request->has('location') && $request->location !== 'All') {
                $query->where('home_location', $request->location);
            }
            
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('email', 'LIKE', "%{$search}%")
                      ->orWhere('phone', 'LIKE', "%{$search}%");
                });
            }
            
            // Apply sorting
            $sortBy = $request->get('sortBy', 'created_at');
            $sortOrder = $request->get('sortOrder', 'desc');
            $query->orderBy($sortBy, $sortOrder);
            
            // Get customers
            $customers = $query->get();
            
            // Get pagination meta information
            $meta = [
                'total' => Customer::count(),
                'filtered_total' => $customers->count(),
                'active_customers' => Customer::where('status', 'Active')->count(),
                'inactive_customers' => Customer::where('status', 'Inactive')->count(),
                'paused_customers' => Customer::where('status', 'Paused')->count()
            ];
            
            return $this->successResponse([
                'customers' => $customers,
                'meta' => $meta
            ], 'Customers retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get customers: ' . $e->getMessage());
        }
    }

    /**
     * Get specific customer details
     */
    public function getCustomer(Request $request, $id): JsonResponse
    {
        try {
            $customer = Customer::findOrFail($id);
            
            return $this->successResponse(
                $customer,
                'Customer retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get customer: ' . $e->getMessage());
        }
    }

    /**
     * Delete customer (Admin only)
     */
    public function deleteCustomer(Request $request, $id): JsonResponse
    {
        try {
            $customer = Customer::findOrFail($id);
            $customerName = $customer->name;
            
            $customer->delete();
            
            return $this->successResponse(
                null,
                "Customer '{$customerName}' deleted successfully"
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete customer: ' . $e->getMessage());
        }
    }

    /**
     * Get customer statistics
     */
    public function getCustomerStatistics(Request $request): JsonResponse
    {
        try {
            $stats = [
                'total_customers' => Customer::count(),
                'active_customers' => Customer::where('status', 'Active')->count(),
                'inactive_customers' => Customer::where('status', 'Inactive')->count(),
                'paused_customers' => Customer::where('status', 'Paused')->count(),
                'new_customers_today' => Customer::whereDate('created_at', today())->count(),
                'new_customers_this_week' => Customer::whereBetween('created_at', [
                    now()->startOfWeek(),
                    now()->endOfWeek()
                ])->count(),
                'new_customers_this_month' => Customer::whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count(),
                'total_loyalty_points' => Customer::sum('loyalty_points'),
                'total_orders' => Customer::sum('total_orders'),
                'total_revenue' => Customer::sum('total_spent'),
                'average_order_value' => Customer::where('total_orders', '>', 0)
                    ->avg('total_spent'),
                'top_locations' => Customer::selectRaw('home_location, COUNT(*) as customer_count')
                    ->whereNotNull('home_location')
                    ->groupBy('home_location')
                    ->orderByDesc('customer_count')
                    ->limit(5)
                    ->get()
            ];
            
            return $this->successResponse(
                $stats,
                'Customer statistics retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get customer statistics: ' . $e->getMessage());
        }
    }

    /**
     * Create new employee with direct dashboard access (admin only)
     */
    public function createEmployee(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|email|unique:employees,email',
                'password' => 'required|string|min:6',
                'phone' => 'required|string|max:50', // Support international formats
                'position' => 'required|string|max:255',
                'department' => 'nullable|string|max:255',
                'location_id' => 'required|exists:locations,id'
            ]);

            // Get the location name for the employee record
            $location = Location::findOrFail($validatedData['location_id']);

            // Create employee with approved status and active stage (bypass onboarding)
            $employee = Employee::create([
                'first_name' => $validatedData['first_name'],
                'last_name' => $validatedData['last_name'],
                'email' => $validatedData['email'],
                'password' => Hash::make($validatedData['password']),
                'phone' => $validatedData['phone'],
                'position' => $validatedData['position'],
                'department' => $validatedData['department'] ?? 'Front of House',
                'location' => $location->name,
                'stage' => Employee::STAGE_ACTIVE,
                'status' => Employee::STATUS_APPROVED,
                'approved_at' => now(),
                'approved_by' => $request->user()->id
            ]);

            return $this->successResponse(
                $employee->load('approvedBy'),
                'Employee created successfully with direct dashboard access',
                201
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), 'Validation failed');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create employee: ' . $e->getMessage());
        }
    }
}
