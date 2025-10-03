<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create the restaurant owner (highest level access)
        Admin::create([
            'first_name' => 'Restaurant',
            'last_name' => 'Owner',
            'email' => 'admin@woodfire.food.com',
            'password' => 'WoodfireFood2025!',
            'phone' => '+1234567890',
            'role' => Admin::ROLE_OWNER,
            'status' => Admin::STATUS_ACTIVE,
            'profile_data' => [
                'permissions' => [Admin::PERMISSION_FULL_ACCESS],
                'created_by' => 'system',
                'notes' => 'Restaurant Owner - Full System Access',
                'company_info' => [
                    'business_name' => 'Woodfire Restaurant',
                    'position' => 'Owner/Proprietor',
                    'access_level' => 'unlimited'
                ]
            ],
            'email_verified_at' => now(),
        ]);
    }
}
