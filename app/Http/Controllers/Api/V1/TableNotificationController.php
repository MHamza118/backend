<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TableNotification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class TableNotificationController extends Controller
{
    /**
     * Get admin notifications
     */
    public function getAdminNotifications(Request $request): JsonResponse
    {
        try {
            $notifications = TableNotification::forAdmin()
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Admin notifications retrieved successfully',
                'data' => [
                    'notifications' => $notifications
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve admin notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get employee notifications
     */
    public function getEmployeeNotifications(Request $request): JsonResponse
    {
        try {
            // Get all employee notifications (global) or specific to this employee
            $employeeId = Auth::id();
            
            $notifications = TableNotification::forEmployee()
                ->where(function ($query) use ($employeeId) {
                    $query->whereNull('recipient_id') // Global employee notifications
                          ->orWhere('recipient_id', $employeeId); // Specific employee notifications
                })
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Employee notifications retrieved successfully',
                'data' => [
                    'notifications' => $notifications
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employee notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark admin notification as read
     */
    public function markAdminNotificationAsRead($notificationId): JsonResponse
    {
        try {
            $notification = TableNotification::forAdmin()
                ->where('id', $notificationId)
                ->first();

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found'
                ], 404);
            }

            $notification->markAsRead();

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notification as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark employee notification as read
     */
    public function markEmployeeNotificationAsRead($notificationId): JsonResponse
    {
        try {
            $employeeId = Auth::id();
            
            $notification = TableNotification::forEmployee()
                ->where('id', $notificationId)
                ->where(function ($query) use ($employeeId) {
                    $query->whereNull('recipient_id') // Global employee notifications
                          ->orWhere('recipient_id', $employeeId); // Specific employee notifications
                })
                ->first();

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found'
                ], 404);
            }

            $notification->markAsRead();

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notification as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark all employee notifications as read
     */
    public function markAllEmployeeNotificationsAsRead(Request $request): JsonResponse
    {
        try {
            $employeeId = Auth::id();
            
            $notifications = TableNotification::forEmployee()
                ->where(function ($query) use ($employeeId) {
                    $query->whereNull('recipient_id') // Global employee notifications
                          ->orWhere('recipient_id', $employeeId); // Specific employee notifications
                })
                ->unread()
                ->get();

            foreach ($notifications as $notification) {
                $notification->markAsRead();
            }

            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read successfully',
                'data' => [
                    'marked_count' => $notifications->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark all notifications as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete admin notification
     */
    public function deleteAdminNotification($notificationId): JsonResponse
    {
        try {
            $notification = TableNotification::forAdmin()
                ->where('id', $notificationId)
                ->first();

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found'
                ], 404);
            }

            $notification->delete();

            return response()->json([
                'success' => true,
                'message' => 'Notification deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete employee notification
     */
    public function deleteEmployeeNotification($notificationId): JsonResponse
    {
        try {
            $employeeId = Auth::id();
            
            $notification = TableNotification::forEmployee()
                ->where('id', $notificationId)
                ->where(function ($query) use ($employeeId) {
                    $query->whereNull('recipient_id') // Global employee notifications
                          ->orWhere('recipient_id', $employeeId); // Specific employee notifications
                })
                ->first();

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found'
                ], 404);
            }

            $notification->delete();

            return response()->json([
                'success' => true,
                'message' => 'Notification deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
