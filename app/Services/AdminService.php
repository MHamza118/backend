<?php

namespace App\Services;

use App\Models\Admin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminService
{
    /**
     * Login admin only - rejects employee users
     */
    public function login(string $email, string $password): array
    {
        // First check if this email belongs to an employee (should not be allowed)
        $employee = \App\Models\Employee::where('email', $email)->first();
        if ($employee) {
            throw ValidationException::withMessages([
                'email' => ['This email is registered as an employee. Please use the Employee Login form.'],
            ]);
        }

        $admin = Admin::where('email', $email)->first();

        if (!$admin || !Hash::check($password, $admin->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid admin credentials. Please check your email and password.'],
            ]);
        }

        if (!$admin->canAccessDashboard()) {
            throw ValidationException::withMessages([
                'email' => ['Your admin account is not active. Please contact support.'],
            ]);
        }

        // Update last login
        $admin->update(['last_login_at' => now()]);

        // Create token
        $token = $admin->createToken('admin-token')->plainTextToken;

        return [
            'admin' => $admin,
            'token' => $token,
            'role' => $admin->role,
            'status' => $admin->status,
            'can_access_dashboard' => true
        ];
    }

    /**
     * Get admin profile with additional data
     */
    public function getProfile(int $adminId): array
    {
        $admin = Admin::findOrFail($adminId);

        return [
            'admin' => $admin,
            'permissions' => $admin->profile_data['permissions'] ?? [],
            'last_login' => $admin->last_login_at,
            'dashboard_access' => $admin->canAccessDashboard(),
        ];
    }

    /**
     * Create a new admin/manager user (Only OWNER can do this)
     */
    public function createAdminUser(Admin $createdBy, array $userData): array
    {
        // Only owners can create other admins
        if (!$createdBy->isOwner()) {
            throw ValidationException::withMessages([
                'role' => ['Only restaurant owners can create admin users.'],
            ]);
        }

        $admin = Admin::create([
            'first_name' => $userData['first_name'],
            'last_name' => $userData['last_name'],
            'email' => $userData['email'],
            'password' => $userData['password'],
            'phone' => $userData['phone'] ?? null,
            'role' => $userData['role'],
            'location_id' => $userData['location_id'] ?? null,
            'department' => $userData['department'] ?? null,
            'notes' => $userData['notes'] ?? null,
            'status' => Admin::STATUS_ACTIVE,
            'profile_data' => [
                'permissions' => Admin::getDefaultPermissions($userData['role']),
                'created_by' => $createdBy->id,
                'created_by_name' => $createdBy->full_name,
            ],
            'email_verified_at' => now(),
        ]);

        return [
            'admin' => $admin,
            'token' => null, // No auto-login for created users
            'message' => ucfirst($userData['role']) . ' user created successfully'
        ];
    }

    /**
     * Get all admin users (with role-based filtering)
     */
    public function getAdminUsers(Admin $requestingAdmin, array $filters = []): array
    {
        $query = Admin::query();

        // Role-based access control
        if ($requestingAdmin->isOwner()) {
            // Owner can see all
        } elseif ($requestingAdmin->isAdmin()) {
            // Admin can see managers only
            $query->where('role', Admin::ROLE_MANAGER);
        } else {
            // Managers can't see other admin users
            $query->whereRaw('1 = 0'); // Return empty result
        }

        // Apply filters
        if (isset($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $admins = $query->with('location')->orderBy('created_at', 'desc')->get();

        return [
            'admins' => $admins,
            'total' => $admins->count(),
            'can_create' => $requestingAdmin->canManageAdmins() || $requestingAdmin->canManageManagers(),
            'requesting_user_role' => $requestingAdmin->role,
        ];
    }

    /**
     * Update admin user permissions (role hierarchy respected)
     */
    public function updateAdminPermissions(Admin $updatingAdmin, int $targetAdminId, array $permissions): array
    {
        $targetAdmin = Admin::findOrFail($targetAdminId);

        // Check role hierarchy - can't modify users of equal or higher role
        if ($updatingAdmin->getRoleLevel() <= $targetAdmin->getRoleLevel()) {
            throw ValidationException::withMessages([
                'permissions' => ['You cannot modify users of equal or higher role level.'],
            ]);
        }

        // Update permissions
        $profileData = $targetAdmin->profile_data;
        $profileData['permissions'] = $permissions;
        $profileData['last_modified_by'] = $updatingAdmin->id;
        $profileData['last_modified_at'] = now()->toISOString();

        $targetAdmin->update(['profile_data' => $profileData]);

        return [
            'admin' => $targetAdmin->fresh(),
            'message' => 'Permissions updated successfully'
        ];
    }

    /**
     * Deactivate/Delete admin user
     */
    public function deactivateAdminUser(Admin $deactivatingAdmin, int $targetAdminId): array
    {
        $targetAdmin = Admin::findOrFail($targetAdminId);

        // Can't deactivate yourself
        if ($deactivatingAdmin->id === $targetAdminId) {
            throw ValidationException::withMessages([
                'user' => ['You cannot deactivate your own account.'],
            ]);
        }

        // Can't deactivate users of equal or higher role
        if ($deactivatingAdmin->getRoleLevel() <= $targetAdmin->getRoleLevel()) {
            throw ValidationException::withMessages([
                'user' => ['You cannot deactivate users of equal or higher role level.'],
            ]);
        }

        $targetAdmin->update(['status' => Admin::STATUS_INACTIVE]);

        return [
            'admin' => $targetAdmin->fresh(),
            'message' => 'User deactivated successfully'
        ];
    }

    /**
     * Get available roles and permissions for role assignment
     */
    public function getAvailableRolesAndPermissions(Admin $admin): array
    {
        $availableRoles = [];
        $allPermissions = Admin::getAllPermissions();

        if ($admin->isOwner()) {
            $availableRoles = [Admin::ROLE_ADMIN, Admin::ROLE_MANAGER];
        } elseif ($admin->isAdmin()) {
            $availableRoles = [Admin::ROLE_MANAGER];
        }

        return [
            'available_roles' => $availableRoles,
            'all_permissions' => $allPermissions,
            'role_defaults' => [
                Admin::ROLE_OWNER => Admin::getDefaultPermissions(Admin::ROLE_OWNER),
                Admin::ROLE_ADMIN => Admin::getDefaultPermissions(Admin::ROLE_ADMIN),
                Admin::ROLE_MANAGER => Admin::getDefaultPermissions(Admin::ROLE_MANAGER),
            ],
            'current_user_role' => $admin->role,
            'current_user_permissions' => $admin->profile_data['permissions'] ?? [],
        ];
    }
}
