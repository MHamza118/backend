<?php

namespace Database\Seeders;

use App\Models\Questionnaire;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class QuestionnaireSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a default admin user if none exists
        $admin = User::firstOrCreate(
            ['email' => 'admin@restaurant.com'],
            [
                'name' => 'Admin User',
                'password' => bcrypt('password123'),
                'email_verified_at' => now(),
            ]
        );

        // Create default onboarding questionnaire
        Questionnaire::create([
            'title' => 'Employee Onboarding Questionnaire',
            'description' => 'Please complete this questionnaire to help us understand your background and preferences.',
            'questions' => [
                [
                    'question' => 'Who conducted your Interview?',
                    'type' => 'single_choice',
                    'options' => ['David', 'Camren', 'Adrienne', 'Other'],
                    'required' => true
                ],
                [
                    'question' => 'Full Name',
                    'type' => 'text',
                    'required' => true
                ],
                [
                    'question' => 'Phone Number',
                    'type' => 'text',
                    'required' => true
                ],
                [
                    'question' => 'Email Address',
                    'type' => 'text',
                    'required' => true
                ],
                [
                    'question' => 'Please provide any days or times you can NOT work over the next 7 days',
                    'type' => 'text',
                    'required' => false
                ],
                [
                    'question' => 'You will be added to the 7shifts software, look for an email and text. Your assigned shift will be sent to you through this software.',
                    'type' => 'text',
                    'required' => false
                ],
                [
                    'question' => 'Have you ever used the 7shifts scheduling and task list software before?',
                    'type' => 'boolean',
                    'required' => true
                ],
                [
                    'question' => 'Have you been hired for front of house or back of house?',
                    'type' => 'single_choice',
                    'options' => ['Front of House', 'Back of House'],
                    'required' => true
                ],
                [
                    'question' => 'I am looking for (Please select all that apply)',
                    'type' => 'multiple_choice',
                    'options' => ['Day Hours', 'Evening Hours', 'Full Time Hours', 'Part Time Hours'],
                    'required' => true
                ],
                [
                    'question' => 'If you have experience in these areas, please provide the duration',
                    'type' => 'single_choice',
                    'options' => ['Under 1 year', '1-2 years', '2+ years', 'No Experience'],
                    'required' => true
                ],
                [
                    'question' => 'I understand the working interview process and that I can paid minimum wage until I am officially brought onto the team.',
                    'type' => 'boolean',
                    'required' => true
                ],
                [
                    'question' => 'Driver\'s License',
                    'type' => 'file',
                    'description' => 'Click to upload your driver\'s license',
                    'accept' => '.pdf,.jpg,.jpeg,.png,.gif,.doc,.docx',
                    'max_size' => '10MB',
                    'required' => true
                ]
            ],
            'steps' => null,
            'settings' => null,
            'is_active' => true,
            'order_index' => 1,
            'created_by' => $admin->id
        ]);
    }
}
