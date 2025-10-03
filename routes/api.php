<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// API V1 Routes
Route::prefix('v1')->group(function () {
    // Authentication routes (public)
    Route::prefix('auth')->group(function () {
        Route::post('register', [App\Http\Controllers\Api\V1\AuthController::class, 'register']);
        Route::post('login', [App\Http\Controllers\Api\V1\AuthController::class, 'login']);
        Route::post('customer/register', [App\Http\Controllers\Api\V1\CustomerController::class, 'register']);
        Route::post('customer/login', [App\Http\Controllers\Api\V1\AuthController::class, 'customerLogin']);
        
        // Employee authentication routes
        Route::post('employee/register', [App\Http\Controllers\Api\V1\EmployeeController::class, 'register']);
        Route::post('employee/login', [App\Http\Controllers\Api\V1\EmployeeController::class, 'employeeLogin']);
        
        // Admin authentication routes
        Route::post('admin/login', [App\Http\Controllers\Api\V1\AdminController::class, 'login']);
        
        // Logout route (protected)
        Route::middleware('auth:sanctum')->post('logout', [App\Http\Controllers\Api\V1\AuthController::class, 'logout']);
    });

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        // User profile routes
        Route::prefix('user')->group(function () {
            Route::get('profile', [App\Http\Controllers\Api\V1\UserController::class, 'profile']);
            Route::put('profile', [App\Http\Controllers\Api\V1\UserController::class, 'updateProfile']);
            Route::post('logout', [App\Http\Controllers\Api\V1\AuthController::class, 'logout']);
        });

        // Customer routes (for authenticated customers)
        Route::prefix('customer')->group(function () {
            Route::get('profile', [App\Http\Controllers\Api\V1\CustomerController::class, 'profile']);
            Route::put('profile', [App\Http\Controllers\Api\V1\CustomerController::class, 'updateProfile']);
            Route::get('dashboard', [App\Http\Controllers\Api\V1\CustomerController::class, 'dashboard']);
            Route::get('announcements', [App\Http\Controllers\Api\V1\CustomerController::class, 'announcements']);
            Route::post('announcements/{id}/dismiss', [App\Http\Controllers\Api\V1\CustomerController::class, 'dismissAnnouncement']);
            Route::post('rewards/redeem', [App\Http\Controllers\Api\V1\CustomerController::class, 'redeemReward']);
            Route::put('preferences', [App\Http\Controllers\Api\V1\CustomerController::class, 'updatePreferences']);
        });

// Employee routes (for authenticated employees)
Route::prefix('employee')->group(function () {
    Route::get('profile', [App\Http\Controllers\Api\V1\EmployeeController::class, 'profile']);
    Route::put('personal-info', [App\Http\Controllers\Api\V1\EmployeeController::class, 'updatePersonalInfo']);
    Route::get('qr', [App\Http\Controllers\Api\V1\EmployeeController::class, 'generateQR']);
    Route::post('location', [App\Http\Controllers\Api\V1\EmployeeController::class, 'submitLocation']);
    Route::get('questionnaire', [App\Http\Controllers\Api\V1\EmployeeController::class, 'getQuestionnaire']);
    Route::post('questionnaire', [App\Http\Controllers\Api\V1\EmployeeController::class, 'submitQuestionnaire']);
    Route::get('welcome', [App\Http\Controllers\Api\V1\EmployeeController::class, 'getWelcomePage']);
    Route::get('confirmation', [App\Http\Controllers\Api\V1\EmployeeController::class, 'getConfirmationPage']);
    Route::get('dashboard', [App\Http\Controllers\Api\V1\EmployeeController::class, 'dashboard']);
    
    // Employee FAQ routes
    Route::prefix('faqs')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\V1\FaqController::class, 'index']);
        Route::get('/categories', [App\Http\Controllers\Api\V1\FaqController::class, 'categories']);
    });
            
            // Onboarding routes
            Route::get('onboarding-pages', [App\Http\Controllers\Api\V1\EmployeeController::class, 'getOnboardingPages']);
            Route::post('onboarding-pages/complete', [App\Http\Controllers\Api\V1\EmployeeController::class, 'completeOnboardingPage']);
            Route::get('onboarding-progress', [App\Http\Controllers\Api\V1\EmployeeController::class, 'getOnboardingProgress']);
            
            // Training routes
            Route::prefix('training')->group(function () {
                Route::get('/', [App\Http\Controllers\Api\V1\EmployeeController::class, 'getTrainingModules']);
                Route::post('/unlock', [App\Http\Controllers\Api\V1\EmployeeController::class, 'unlockTrainingModule']);
                Route::get('/{moduleId}/content', [App\Http\Controllers\Api\V1\EmployeeController::class, 'getTrainingContent']);
                Route::post('/{moduleId}/complete', [App\Http\Controllers\Api\V1\EmployeeController::class, 'completeTraining']);
            });
            
            // Alias routes for frontend compatibility
            Route::get('training-modules', [App\Http\Controllers\Api\V1\EmployeeController::class, 'getTrainingModules']);
            Route::post('training-modules/unlock', [App\Http\Controllers\Api\V1\EmployeeController::class, 'unlockTrainingModule']);
            Route::get('training-modules/{moduleId}/content', [App\Http\Controllers\Api\V1\EmployeeController::class, 'getTrainingContent']);
            Route::post('training-modules/{moduleId}/complete', [App\Http\Controllers\Api\V1\EmployeeController::class, 'completeTraining']);
            
            Route::post('logout', [App\Http\Controllers\Api\V1\EmployeeController::class, 'logout']);
        });

// Admin dashboard and profile routes
Route::prefix('admin')->middleware(['admin'])->group(function () {
    Route::get('dashboard', [App\Http\Controllers\Api\V1\AdminController::class, 'dashboard']);
    Route::get('profile', [App\Http\Controllers\Api\V1\AdminController::class, 'profile']);
    Route::post('logout', [App\Http\Controllers\Api\V1\AdminController::class, 'logout']);
    
    // Admin FAQ management routes
    Route::prefix('faqs')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\V1\FaqController::class, 'adminIndex']);
        Route::post('/', [App\Http\Controllers\Api\V1\FaqController::class, 'store']);
        Route::get('/categories', [App\Http\Controllers\Api\V1\FaqController::class, 'categories']);
        Route::put('/order', [App\Http\Controllers\Api\V1\FaqController::class, 'updateOrder']);
        Route::get('/{id}', [App\Http\Controllers\Api\V1\FaqController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\Api\V1\FaqController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\Api\V1\FaqController::class, 'destroy']);
        Route::patch('/{id}/toggle-status', [App\Http\Controllers\Api\V1\FaqController::class, 'toggleActive']);
    });
            
            // Admin user management routes (role-based permissions)
            Route::prefix('users')->group(function () {
                Route::get('/', [App\Http\Controllers\Api\V1\AdminController::class, 'getAdminUsers']);
                Route::post('/', [App\Http\Controllers\Api\V1\AdminController::class, 'createAdminUser'])->middleware('permission:full_access');
                Route::put('/{id}/permissions', [App\Http\Controllers\Api\V1\AdminController::class, 'updateAdminPermissions']);
                Route::delete('/{id}', [App\Http\Controllers\Api\V1\AdminController::class, 'deactivateAdminUser']);
            });
            
            // Roles and permissions reference
            Route::get('roles-permissions', [App\Http\Controllers\Api\V1\AdminController::class, 'getRolesAndPermissions']);
            
            // Location management routes
            Route::prefix('locations')->group(function () {
                Route::get('/', [App\Http\Controllers\Api\V1\AdminController::class, 'getLocations']);
                Route::post('/', [App\Http\Controllers\Api\V1\AdminController::class, 'createLocation']);
                Route::put('/{id}', [App\Http\Controllers\Api\V1\AdminController::class, 'updateLocation']);
                Route::delete('/{id}', [App\Http\Controllers\Api\V1\AdminController::class, 'deleteLocation']);
            });
            
            // Admin customer management routes
            Route::get('customers', [App\Http\Controllers\Api\V1\CustomerController::class, 'index']);
            Route::post('customers', [App\Http\Controllers\Api\V1\AdminController::class, 'createCustomer']);
            Route::get('customers/statistics', [App\Http\Controllers\Api\V1\CustomerController::class, 'statistics']);
            Route::get('customers/{id}', [App\Http\Controllers\Api\V1\CustomerController::class, 'show']);
            Route::put('customers/{id}', [App\Http\Controllers\Api\V1\AdminController::class, 'updateCustomer']);
            Route::delete('customers/{id}', [App\Http\Controllers\Api\V1\CustomerController::class, 'destroy']);
            
            // Admin employee management routes
            Route::prefix('employees')->group(function () {
                Route::get('/', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'index']);
                Route::post('/', [App\Http\Controllers\Api\V1\AdminController::class, 'createEmployee']);
                Route::get('/pending', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'pendingApproval']);
                Route::get('/statistics', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'statistics']);
                Route::get('/{id}', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'show']);
                Route::post('/{id}/approve', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'approve']);
                Route::post('/{id}/reject', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'reject']);
                // Lifecycle actions
                Route::post('/{id}/pause', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'pause']);
                Route::post('/{id}/resume', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'resume']);
                Route::post('/{id}/deactivate', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'deactivate']);
                Route::post('/{id}/activate', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'activate']);
            });
            
            // Admin questionnaire management routes
            Route::prefix('questionnaires')->group(function () {
                Route::get('/', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'questionnaires']);
                Route::post('/', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'createQuestionnaire']);
                Route::get('/{id}', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'showQuestionnaire']);
                Route::put('/{id}', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'updateQuestionnaire']);
                Route::delete('/{id}', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'deleteQuestionnaire']);
                Route::post('/{id}/toggle', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'toggleQuestionnaireStatus']);
            });
            
            // Admin onboarding page management routes
            Route::prefix('onboarding-pages')->group(function () {
                Route::get('/', [App\Http\Controllers\Api\V1\AdminController::class, 'getOnboardingPages']);
                Route::post('/', [App\Http\Controllers\Api\V1\AdminController::class, 'createOnboardingPage']);
                Route::put('/{id}', [App\Http\Controllers\Api\V1\AdminController::class, 'updateOnboardingPage']);
                Route::delete('/{id}', [App\Http\Controllers\Api\V1\AdminController::class, 'deleteOnboardingPage']);
                Route::patch('/{id}/toggle-status', [App\Http\Controllers\Api\V1\AdminController::class, 'toggleOnboardingPageStatus']);
            });
            
            // Admin training module management routes
            Route::prefix('training-modules')->group(function () {
                Route::get('/', [App\Http\Controllers\Api\V1\TrainingModuleController::class, 'index']);
                Route::post('/', [App\Http\Controllers\Api\V1\TrainingModuleController::class, 'store']);
                Route::get('/categories', [App\Http\Controllers\Api\V1\TrainingModuleController::class, 'getCategories']);
                Route::get('/analytics', [App\Http\Controllers\Api\V1\TrainingModuleController::class, 'getAnalytics']);
                Route::get('/{id}', [App\Http\Controllers\Api\V1\TrainingModuleController::class, 'show']);
                Route::put('/{id}', [App\Http\Controllers\Api\V1\TrainingModuleController::class, 'update']);
                Route::delete('/{id}', [App\Http\Controllers\Api\V1\TrainingModuleController::class, 'destroy']);
                
                // Assignment management
                Route::post('/{id}/assign', [App\Http\Controllers\Api\V1\TrainingModuleController::class, 'assignToEmployees']);
                Route::get('/{id}/assignments', [App\Http\Controllers\Api\V1\TrainingModuleController::class, 'getAssignments']);
                Route::post('/{id}/reset-progress', [App\Http\Controllers\Api\V1\TrainingModuleController::class, 'resetProgress']);
                Route::post('/{id}/remove-assignments', [App\Http\Controllers\Api\V1\TrainingModuleController::class, 'removeAssignments']);
                
                // QR Code management
                Route::get('/{id}/qr-code', [App\Http\Controllers\Api\V1\TrainingModuleController::class, 'generateQrCode']);
                Route::post('/{id}/regenerate-qr', [App\Http\Controllers\Api\V1\TrainingModuleController::class, 'regenerateQrCode']);
            });
            
            // Admin ticket management routes
            Route::prefix('tickets')->group(function () {
                Route::get('/', [App\Http\Controllers\Api\V1\TicketController::class, 'index']);
                Route::get('/statistics', [App\Http\Controllers\Api\V1\TicketController::class, 'statistics']);
                Route::get('/configuration', [App\Http\Controllers\Api\V1\TicketController::class, 'configuration']);
                Route::get('/{id}', [App\Http\Controllers\Api\V1\TicketController::class, 'show']);
                Route::put('/{id}', [App\Http\Controllers\Api\V1\TicketController::class, 'update']);
                Route::post('/{id}/archive', [App\Http\Controllers\Api\V1\TicketController::class, 'archive']);
                Route::delete('/{id}', [App\Http\Controllers\Api\V1\TicketController::class, 'destroy']);
                Route::post('/{id}/responses', [App\Http\Controllers\Api\V1\TicketController::class, 'addResponse']);
            });

            // Admin time-off management routes
            Route::prefix('time-off')->group(function () {
                Route::get('/', [App\Http\Controllers\Api\V1\TimeOffController::class, 'adminIndex']);
                Route::get('/{id}', [App\Http\Controllers\Api\V1\TimeOffController::class, 'show']);
                Route::post('/{id}/status', [App\Http\Controllers\Api\V1\TimeOffController::class, 'updateStatus']);
            });
            
            // Admin table tracking management routes
            Route::prefix('table-tracking')->group(function () {
                Route::get('/orders', [App\Http\Controllers\Api\V1\TableTrackingController::class, 'getAllOrders']);
                Route::post('/orders', [App\Http\Controllers\Api\V1\TableTrackingController::class, 'addOrder']);
                Route::put('/orders/{orderNumber}/status', [App\Http\Controllers\Api\V1\TableTrackingController::class, 'updateOrderStatus']);
                Route::put('/orders/{orderNumber}/delivered', [App\Http\Controllers\Api\V1\TableTrackingController::class, 'markDelivered']);
                Route::delete('/orders/{orderNumber}', [App\Http\Controllers\Api\V1\TableTrackingController::class, 'deleteOrder']);
                
                Route::get('/mappings', [App\Http\Controllers\Api\V1\TableTrackingController::class, 'getAllMappings']);
                Route::post('/manual-mapping', [App\Http\Controllers\Api\V1\TableTrackingController::class, 'submitManualMapping']);
                Route::put('/mappings/{orderNumber}/clear', [App\Http\Controllers\Api\V1\TableTrackingController::class, 'clearMapping']);
                
                Route::get('/analytics', [App\Http\Controllers\Api\V1\TableTrackingController::class, 'getAnalytics']);
                
                Route::get('/notifications', [App\Http\Controllers\Api\V1\TableNotificationController::class, 'getAdminNotifications']);
                Route::put('/notifications/{notificationId}/read', [App\Http\Controllers\Api\V1\TableNotificationController::class, 'markAdminNotificationAsRead']);
                Route::delete('/notifications/{notificationId}', [App\Http\Controllers\Api\V1\TableNotificationController::class, 'deleteAdminNotification']);
            });
            
            // Admin communication routes
            Route::prefix('communication')->group(function () {
                Route::get('/conversations', [App\Http\Controllers\Api\V1\ConversationController::class, 'index']);
                Route::post('/conversations/group', [App\Http\Controllers\Api\V1\ConversationController::class, 'createGroup']);
                Route::post('/conversations/private', [App\Http\Controllers\Api\V1\ConversationController::class, 'getOrCreatePrivateConversation']);
                Route::post('/conversations/{id}/participants', [App\Http\Controllers\Api\V1\ConversationController::class, 'addParticipants']);
                Route::post('/conversations/{id}/read', [App\Http\Controllers\Api\V1\ConversationController::class, 'markAsRead']);
                Route::get('/conversations/{id}/messages', [App\Http\Controllers\Api\V1\MessageController::class, 'index']);
                Route::post('/conversations/{id}/messages', [App\Http\Controllers\Api\V1\MessageController::class, 'store']);
                Route::post('/messages/group', [App\Http\Controllers\Api\V1\MessageController::class, 'sendGroupMessage']);
                Route::post('/messages/private', [App\Http\Controllers\Api\V1\MessageController::class, 'sendPrivateMessage']);
            });
        });

        // Employee ticket routes
        Route::prefix('employee')->group(function () {
            Route::prefix('tickets')->group(function () {
                Route::get('/', [App\Http\Controllers\Api\V1\TicketController::class, 'employeeTickets']);
                Route::post('/', [App\Http\Controllers\Api\V1\TicketController::class, 'store']);
                Route::get('/configuration', [App\Http\Controllers\Api\V1\TicketController::class, 'configuration']);
                Route::get('/{id}', [App\Http\Controllers\Api\V1\TicketController::class, 'show']);
            });

            // Employee time-off routes
            Route::prefix('time-off')->group(function () {
                Route::get('/', [App\Http\Controllers\Api\V1\TimeOffController::class, 'employeeIndex']);
                Route::post('/', [App\Http\Controllers\Api\V1\TimeOffController::class, 'store']);
                Route::get('/{id}', [App\Http\Controllers\Api\V1\TimeOffController::class, 'show']);
                Route::post('/{id}/cancel', [App\Http\Controllers\Api\V1\TimeOffController::class, 'cancel']);
            });
            
            // Employee table tracking routes
            Route::prefix('table-tracking')->group(function () {
                Route::get('/orders', [App\Http\Controllers\Api\V1\TableTrackingController::class, 'getEmployeeOrders']);
                Route::get('/mappings', [App\Http\Controllers\Api\V1\TableTrackingController::class, 'getEmployeeMappings']);
                
                // Employee can update order status and mark delivered
                Route::put('/orders/{orderNumber}/status', [App\Http\Controllers\Api\V1\TableTrackingController::class, 'updateEmployeeOrderStatus']);
                Route::put('/orders/{orderNumber}/delivered', [App\Http\Controllers\Api\V1\TableTrackingController::class, 'markEmployeeDelivered']);
                
                Route::get('/notifications', [App\Http\Controllers\Api\V1\TableNotificationController::class, 'getEmployeeNotifications']);
                Route::put('/notifications/{notificationId}/read', [App\Http\Controllers\Api\V1\TableNotificationController::class, 'markEmployeeNotificationAsRead']);
                Route::put('/notifications/mark-all-read', [App\Http\Controllers\Api\V1\TableNotificationController::class, 'markAllEmployeeNotificationsAsRead']);
                Route::delete('/notifications/{notificationId}', [App\Http\Controllers\Api\V1\TableNotificationController::class, 'deleteEmployeeNotification']);
            });
            
            // Employee communication routes
            Route::prefix('communication')->group(function () {
                Route::get('/conversations', [App\Http\Controllers\Api\V1\ConversationController::class, 'index']);
                Route::post('/conversations/{id}/read', [App\Http\Controllers\Api\V1\ConversationController::class, 'markAsRead']);
                Route::get('/conversations/{id}/messages', [App\Http\Controllers\Api\V1\MessageController::class, 'index']);
                Route::post('/conversations/{id}/messages', [App\Http\Controllers\Api\V1\MessageController::class, 'store']);
                Route::post('/messages/private', [App\Http\Controllers\Api\V1\MessageController::class, 'sendPrivateMessage']);
            });
        });
        
        // File download routes
        Route::get('files/{id}/download', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'downloadFile']);
        
        // Example CRUD routes
        Route::apiResource('posts', App\Http\Controllers\Api\V1\PostController::class);
    });
    
    // Public routes (no auth required)
    Route::get('tickets/configuration', [App\Http\Controllers\Api\V1\TicketController::class, 'configuration']);
    
    // Table tracking public routes (no auth required)
    Route::post('table-tracking/submit', [App\Http\Controllers\Api\V1\TableTrackingController::class, 'submitTableMapping']);
    Route::get('table-tracking/settings', [App\Http\Controllers\Api\V1\TableTrackingController::class, 'getTableSettings']);
});
