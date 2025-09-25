<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;

class Admin extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'phone',
        'role',
        'location_id',
        'department',
        'notes',
        'status',
        'profile_data',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'profile_data' => 'array',
        'password' => 'hashed',
    ];

    protected $dates = ['deleted_at'];

    // Role constants (hierarchy: OWNER > ADMIN > MANAGER > HIRING_MANAGER > EXPO)
    const ROLE_OWNER = 'owner';
    const ROLE_ADMIN = 'admin';
    const ROLE_MANAGER = 'manager';
    const ROLE_HIRING_MANAGER = 'hiring_manager';
    const ROLE_EXPO = 'expo';
    
    // Permission constants
    const PERMISSION_FULL_ACCESS = 'full_access';
    const PERMISSION_MANAGE_ADMINS = 'manage_admins';
    const PERMISSION_MANAGE_MANAGERS = 'manage_managers';
    const PERMISSION_MANAGE_EMPLOYEES = 'manage_employees';
    const PERMISSION_MANAGE_CUSTOMERS = 'manage_customers';
    const PERMISSION_MANAGE_ORDERS = 'manage_orders';
    const PERMISSION_MANAGE_MENU = 'manage_menu';
    const PERMISSION_MANAGE_INVENTORY = 'manage_inventory';
    const PERMISSION_MANAGE_LOCATIONS = 'manage_locations';
    const PERMISSION_MANAGE_SCHEDULES = 'manage_schedules';
    const PERMISSION_MANAGE_PAYROLL = 'manage_payroll';
    const PERMISSION_MANAGE_REPORTS = 'manage_reports';
    const PERMISSION_MANAGE_SETTINGS = 'manage_settings';
    const PERMISSION_VIEW_ANALYTICS = 'view_analytics';
    const PERMISSION_VIEW_FINANCIAL = 'view_financial';
    const PERMISSION_MANAGE_NOTIFICATIONS = 'manage_notifications';

    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_SUSPENDED = 'suspended';

    /**
     * Get full name attribute
     */
    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    /**
     * Check if admin is active
     */
    public function isActive()
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if admin can access dashboard
     */
    public function canAccessDashboard()
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if admin is owner (highest level)
     */
    public function isOwner()
    {
        return $this->role === self::ROLE_OWNER;
    }

    /**
     * Check if admin is admin level
     */
    public function isAdmin()
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Check if admin is manager level
     */
    public function isManager()
    {
        return $this->role === self::ROLE_MANAGER;
    }

    /**
     * Check if admin is hiring manager level
     */
    public function isHiringManager()
    {
        return $this->role === self::ROLE_HIRING_MANAGER;
    }

    /**
     * Check if admin is expo level
     */
    public function isExpo()
    {
        return $this->role === self::ROLE_EXPO;
    }

    /**
     * Get all available permissions for restaurant system
     */
    public static function getAllPermissions()
    {
        return [
            self::PERMISSION_FULL_ACCESS,
            self::PERMISSION_MANAGE_ADMINS,
            self::PERMISSION_MANAGE_MANAGERS,
            self::PERMISSION_MANAGE_EMPLOYEES,
            self::PERMISSION_MANAGE_CUSTOMERS,
            self::PERMISSION_MANAGE_ORDERS,
            self::PERMISSION_MANAGE_MENU,
            self::PERMISSION_MANAGE_INVENTORY,
            self::PERMISSION_MANAGE_LOCATIONS,
            self::PERMISSION_MANAGE_SCHEDULES,
            self::PERMISSION_MANAGE_PAYROLL,
            self::PERMISSION_MANAGE_REPORTS,
            self::PERMISSION_MANAGE_SETTINGS,
            self::PERMISSION_VIEW_ANALYTICS,
            self::PERMISSION_VIEW_FINANCIAL,
            self::PERMISSION_MANAGE_NOTIFICATIONS,
        ];
    }

    /**
     * Get default permissions for each role
     */
    public static function getDefaultPermissions($role)
    {
        switch ($role) {
            case self::ROLE_OWNER:
                return [self::PERMISSION_FULL_ACCESS]; // Owner has full access to everything
                
            case self::ROLE_ADMIN:
                return [
                    self::PERMISSION_MANAGE_MANAGERS,
                    self::PERMISSION_MANAGE_EMPLOYEES,
                    self::PERMISSION_MANAGE_CUSTOMERS,
                    self::PERMISSION_MANAGE_ORDERS,
                    self::PERMISSION_MANAGE_MENU,
                    self::PERMISSION_MANAGE_LOCATIONS,
                    self::PERMISSION_MANAGE_SCHEDULES,
                    self::PERMISSION_MANAGE_REPORTS,
                    self::PERMISSION_VIEW_ANALYTICS,
                    self::PERMISSION_MANAGE_NOTIFICATIONS,
                ];
                
                case self::ROLE_MANAGER:
                return [
                    self::PERMISSION_MANAGE_EMPLOYEES,
                    self::PERMISSION_MANAGE_ORDERS,
                    self::PERMISSION_MANAGE_SCHEDULES,
                    self::PERMISSION_VIEW_ANALYTICS,
                ];
                
            case self::ROLE_HIRING_MANAGER:
                return [
                    self::PERMISSION_MANAGE_EMPLOYEES,
                    self::PERMISSION_MANAGE_SCHEDULES,
                ];
                
            case self::ROLE_EXPO:
                return [
                    self::PERMISSION_MANAGE_ORDERS,
                ];
                
            default:
                return [];
        }
    }

    /**
     * Check if admin has specific permission
     */
    public function hasPermission($permission)
    {
        $permissions = $this->profile_data['permissions'] ?? [];
        
        // Owner with full_access has all permissions
        if (in_array(self::PERMISSION_FULL_ACCESS, $permissions)) {
            return true;
        }
        
        return in_array($permission, $permissions);
    }

    /**
     * Check if admin can manage other users
     */
    public function canManageAdmins()
    {
        return $this->isOwner() || $this->hasPermission(self::PERMISSION_MANAGE_ADMINS);
    }

    /**
     * Check if admin can manage managers
     */
    public function canManageManagers()
    {
        return $this->isOwner() || $this->hasPermission(self::PERMISSION_MANAGE_MANAGERS) || $this->isAdmin();
    }

    /**
     * Get role hierarchy level (higher number = higher authority)
     */
    public function getRoleLevel()
    {
        switch ($this->role) {
            case self::ROLE_OWNER:
                return 5;
            case self::ROLE_ADMIN:
                return 4;
            case self::ROLE_MANAGER:
                return 3;
            case self::ROLE_HIRING_MANAGER:
                return 2;
            case self::ROLE_EXPO:
                return 1;
            default:
                return 0;
        }
    }

    /**
     * Scope for active admins
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope by role
     */
    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Get the location that the admin belongs to
     */
    public function location()
    {
        return $this->belongsTo(Location::class);
    }
}
