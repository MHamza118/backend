<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TableOrder;
use App\Models\TableMapping;
use App\Models\TableNotification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TableTrackingController extends Controller
{
    /**
     * Submit table mapping from customer (no auth required)
     */
    public function submitTableMapping(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'table_number' => 'required|string',
            'order_number' => 'required|string|regex:/^[0-9]+$/'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $tableNumber = strtoupper($request->table_number);
        $orderNumber = $request->order_number;

        // Enforce unique order numbers across the system (backend-level)
        if (TableOrder::where('order_number', $orderNumber)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Order number already exists. Please enter a unique order number.'
            ], 422);
        }

        // Validate table number
        if (!TableMapping::isValidTableNumber($tableNumber)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid table number. Please check the number displayed at your table.'
            ], 422);
        }

        // Rate limiting - prevent spam from SAME table+order combo, but allow different tables with same order
        $recentSubmission = TableMapping::where('order_number', $orderNumber)
            ->where('table_number', $tableNumber)
            ->where('created_at', '>=', now()->subSeconds(30))
            ->exists();

        if ($recentSubmission) {
            return response()->json([
                'success' => false,
                'message' => 'This table and order combination was just submitted. Please wait 30 seconds before submitting again.'
            ], 429);
        }

        try {
            DB::beginTransaction();

            // ALWAYS create new mapping - DO NOT update existing ones
            // Generate unique submission ID to prevent conflicts
            $submissionId = $orderNumber . '_' . $tableNumber . '_' . time() . '_' . uniqid();
            $uniqueIdentifier = 'order_' . $orderNumber . '_' . $tableNumber . '_' . uniqid();
            
            $mapping = TableMapping::create([
                'order_number' => $orderNumber,
                'submission_id' => $submissionId,
                'submitted_at' => now(),
                'table_number' => $tableNumber,
                'area' => TableMapping::getAreaForTable($tableNumber),
                'status' => TableMapping::STATUS_ACTIVE,
                'source' => TableMapping::SOURCE_CUSTOMER,
                'update_count' => 0
            ]);

            // Create SEPARATE order record for THIS specific mapping
            $order = TableOrder::create([
                'order_number' => $orderNumber,
                'unique_identifier' => $uniqueIdentifier,
                'mapping_id' => $mapping->id,
                'table_number' => $tableNumber,
                'area' => TableMapping::getAreaForTable($tableNumber),
                'customer_name' => 'Walk-in Customer',
                'status' => TableOrder::STATUS_PENDING
            ]);

            // Create notifications for admin and employees
            $this->createNotificationsForNewTableSubmission($mapping, $order);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Table mapping submitted successfully',
                'data' => [
                    'mapping' => $mapping,
                    'order' => $order
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit table mapping',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get table settings (valid table numbers, areas) - no auth required
     */
    public function getTableSettings(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Table settings retrieved successfully',
            'data' => [
                'settings' => [
                    'validTableNumbers' => [], // No fixed list - any numeric table number is valid
                    'areas' => [
                        'dining' => 'Any numeric table (e.g., 1, 2, 123)',
                        'patio' => 'P + numbers (e.g., P1, P2, P123)',
                        'bar' => 'B + numbers (e.g., B1, B2, B123)'
                    ],
                    'validation' => [
                        'tableNumber' => 'Must be numeric, P+numeric, or B+numeric',
                        'orderNumber' => 'Must be unique numeric value'
                    ]
                ]
            ]
        ]);
    }

    /**
     * Get all orders for admin
     */
    public function getAllOrders(): JsonResponse
    {
        try {
            $orders = TableOrder::with('mapping')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Orders retrieved successfully',
                'data' => [
                    'orders' => $orders
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve orders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all mappings for admin
     */
    public function getAllMappings(): JsonResponse
    {
        try {
            $mappings = TableMapping::with('specificOrder')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Mappings retrieved successfully',
                'data' => [
                    'mappings' => $mappings
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve mappings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get orders for employees (read-only)
     */
    public function getEmployeeOrders(): JsonResponse
    {
        try {
            $orders = TableOrder::with('mapping')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Employee orders retrieved successfully',
                'data' => [
                    'orders' => $orders
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve orders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get mappings for employees (read-only)
     */
    public function getEmployeeMappings(): JsonResponse
    {
        try {
            $mappings = TableMapping::with('specificOrder')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Employee mappings retrieved successfully',
                'data' => [
                    'mappings' => $mappings
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve mappings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get analytics for admin
     */
    public function getAnalytics(): JsonResponse
    {
        try {
            $today = today();
            
            $analytics = [
                'totalSubmissions' => TableMapping::count(),
                'successfulSubmissions' => TableMapping::where('status', '!=', TableMapping::STATUS_CLEARED)->count(),
                'todaySubmissions' => TableMapping::whereDate('created_at', $today)->count(),
                'todayDeliveries' => TableMapping::where('status', TableMapping::STATUS_DELIVERED)
                    ->whereDate('delivered_at', $today)->count(),
                'activeMappings' => TableMapping::where('status', TableMapping::STATUS_ACTIVE)->count(),
                'averageDeliveryTime' => TableMapping::where('status', TableMapping::STATUS_DELIVERED)
                    ->whereNotNull('delivered_at')
                    ->whereDate('delivered_at', $today)
                    ->get()
                    ->avg('delivery_time_minutes'),
                'dailyStats' => [
                    $today->toDateString() => [
                        'submissions' => TableMapping::whereDate('created_at', $today)->count(),
                        'deliveries' => TableMapping::where('status', TableMapping::STATUS_DELIVERED)
                            ->whereDate('delivered_at', $today)->count(),
                        'averageDeliveryTime' => TableMapping::where('status', TableMapping::STATUS_DELIVERED)
                            ->whereNotNull('delivered_at')
                            ->whereDate('delivered_at', $today)
                            ->get()
                            ->avg('delivery_time_minutes') ?: 0
                    ]
                ]
            ];

            return response()->json([
                'success' => true,
                'message' => 'Analytics retrieved successfully',
                'data' => [
                    'analytics' => $analytics
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add order manually (Admin only)
     */
    public function addOrder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_number' => 'required|string|regex:/^[0-9]+$/',
            'customer_name' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:' . implode(',', TableOrder::getValidStatuses())
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Reject duplicate order numbers
            if (TableOrder::where('order_number', $request->order_number)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order number already exists. Please enter a unique order number.'
                ], 422);
            }

            // Create standalone order with unique identifier
            $uniqueIdentifier = 'order_' . $request->order_number . '_standalone_' . uniqid();
            
            $order = TableOrder::create([
                'order_number' => $request->order_number,
                'unique_identifier' => $uniqueIdentifier,
                'customer_name' => $request->customer_name ?: 'Walk-in Customer',
                'status' => $request->status ?: TableOrder::STATUS_PENDING
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order added successfully',
                'data' => [
                    'order' => $order
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update order status (Admin only) - Updated to handle specific order by mapping ID
     */
    public function updateOrderStatus(Request $request, $orderNumber): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:' . implode(',', TableOrder::getValidStatuses()),
            'mapping_id' => 'nullable|integer', // Add mapping_id to target specific order
            'table_number' => 'nullable|string' // Add table_number as fallback
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Find specific order by mapping_id first, then by order_number + table_number
            $order = null;
            
            if ($request->mapping_id) {
                $order = TableOrder::where('mapping_id', $request->mapping_id)->first();
            } elseif ($request->table_number) {
                $order = TableOrder::where('order_number', $orderNumber)
                    ->where('table_number', $request->table_number)
                    ->first();
            } else {
                // Fallback to first order with this number (legacy support)
                $order = TableOrder::where('order_number', $orderNumber)->first();
            }
            
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found for the specified criteria'
                ], 404);
            }

            $oldStatus = $order->status;
            $order->update(['status' => $request->status]);

            // Get the associated mapping
            $mapping = TableMapping::where('id', $order->mapping_id)->first();

            // Handle delivered status - also mark mapping as delivered
            if ($request->status === TableOrder::STATUS_DELIVERED && $mapping && $mapping->status === TableMapping::STATUS_ACTIVE) {
                $mapping->update([
                    'status' => TableMapping::STATUS_DELIVERED,
                    'delivered_at' => now(),
                    'delivered_by' => auth()->user()->name ?? 'Admin'
                ]);
            }

            // Create status update notifications
            $this->createNotificationsForStatusUpdate($order, $mapping, $oldStatus, $request->status);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order status updated successfully',
                'data' => [
                    'order' => $order->fresh(),
                    'mapping' => $mapping ? $mapping->fresh() : null
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update order status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark order as delivered (Admin only)
     */
    public function markDelivered(Request $request, $orderNumber): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'delivered_by' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $order = TableOrder::where('order_number', $orderNumber)->first();
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            $mapping = $order->mapping;
            if (!$mapping) {
                return response()->json([
                    'success' => false,
                    'message' => 'Table mapping not found for this order'
                ], 404);
            }

            // Update order and mapping
            $order->update(['status' => TableOrder::STATUS_DELIVERED]);
            
            $mapping->update([
                'status' => TableMapping::STATUS_DELIVERED,
                'delivered_at' => now(),
                'delivered_by' => $request->delivered_by ?: (auth()->user()->name ?? 'Admin')
            ]);

            // Create delivery notifications
            $this->createNotificationsForDelivery($order, $mapping);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order marked as delivered successfully',
                'data' => [
                    'order' => $order->fresh(),
                    'mapping' => $mapping->fresh()
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark order as delivered',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete order (Admin only)
     */
    public function deleteOrder(Request $request, $orderNumber): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Find specific order by mapping_id first, then by order_number
            $order = null;
            if ($request->has('mapping_id') && $request->mapping_id) {
                $order = TableOrder::where('mapping_id', $request->mapping_id)->first();
            } elseif ($request->has('table_number') && $request->table_number) {
                $order = TableOrder::where('order_number', $orderNumber)
                    ->where('table_number', $request->table_number)
                    ->first();
            } else {
                // Fallback to first order with this number
                $order = TableOrder::where('order_number', $orderNumber)->first();
            }
            
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found for the specified criteria'
                ], 404);
            }

            // Clear/delete associated mapping
            if ($order->mapping) {
                $order->mapping->update([
                    'status' => TableMapping::STATUS_CLEARED,
                    'cleared_at' => now(),
                    'clear_reason' => 'order_deleted'
                ]);
            }

            // Delete order
            $order->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit manual table mapping (Admin only)
     */
    public function submitManualMapping(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'table_number' => 'required|string',
            'order_number' => 'required|string|regex:/^[0-9]+$/'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $tableNumber = strtoupper($request->table_number);
        $orderNumber = $request->order_number;

        // Enforce unique order numbers across the system for manual mapping as well
        if (TableOrder::where('order_number', $orderNumber)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Order number already exists. Please enter a unique order number.'
            ], 422);
        }

        // Validate table number
        if (!TableMapping::isValidTableNumber($tableNumber)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid table number. Please check the table number and try again.'
            ], 422);
        }

        try {
            DB::beginTransaction();

            // ALWAYS create new mapping - DO NOT update existing ones (Admin Manual Entry)
            // Generate unique submission ID for manual entries
            $submissionId = $orderNumber . '_' . $tableNumber . '_' . time() . '_manual_' . uniqid();
            $uniqueIdentifier = 'order_' . $orderNumber . '_' . $tableNumber . '_manual_' . uniqid();
            
            $mapping = TableMapping::create([
                'order_number' => $orderNumber,
                'submission_id' => $submissionId,
                'submitted_at' => now(),
                'table_number' => $tableNumber,
                'area' => TableMapping::getAreaForTable($tableNumber),
                'status' => TableMapping::STATUS_ACTIVE,
                'source' => TableMapping::SOURCE_ADMIN,
                'update_count' => 0
            ]);

            // Create SEPARATE order record for THIS specific manual mapping
            $order = TableOrder::create([
                'order_number' => $orderNumber,
                'unique_identifier' => $uniqueIdentifier,
                'mapping_id' => $mapping->id,
                'table_number' => $tableNumber,
                'area' => TableMapping::getAreaForTable($tableNumber),
                'customer_name' => 'Walk-in Customer',
                'status' => TableOrder::STATUS_PENDING
            ]);

            // Create notifications
            $this->createNotificationsForManualMapping($mapping, $order);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Manual table mapping submitted successfully',
                'data' => [
                    'mapping' => $mapping,
                    'order' => $order
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit manual mapping',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear mapping (Admin only)
     */
    public function clearMapping(Request $request, $orderNumber): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $mapping = TableMapping::where('order_number', $orderNumber)
                ->where('status', TableMapping::STATUS_ACTIVE)
                ->first();

            if (!$mapping) {
                return response()->json([
                    'success' => false,
                    'message' => 'Active mapping not found for this order'
                ], 404);
            }

            $mapping->update([
                'status' => TableMapping::STATUS_CLEARED,
                'cleared_at' => now(),
                'clear_reason' => $request->reason ?: 'manual_clear'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Mapping cleared successfully',
                'data' => [
                    'mapping' => $mapping->fresh()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear mapping',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create notifications for new table submission
     */
    private function createNotificationsForNewTableSubmission(TableMapping $mapping, TableOrder $order)
    {
        // Admin notification
        TableNotification::create([
            'type' => TableNotification::TYPE_NEW_ORDER,
            'title' => 'New Table Assignment',
            'message' => "Customer at Table {$mapping->table_number} submitted Order #{$order->order_number}",
            'order_number' => $order->order_number,
            'table_number' => $mapping->table_number,
            'customer_name' => $order->customer_name,
            'priority' => TableNotification::PRIORITY_HIGH,
            'recipient_type' => TableNotification::RECIPIENT_ADMIN,
            'data' => [
                'source' => $mapping->source,
                'area' => $mapping->area,
                'customer_name' => $order->customer_name
            ]
        ]);

        // Employee notification (global for all employees)
        TableNotification::create([
            'type' => TableNotification::TYPE_NEW_ORDER,
            'title' => 'New Table Assignment',
            'message' => "Customer seated at Table {$mapping->table_number} with Order #{$order->order_number}",
            'order_number' => $order->order_number,
            'table_number' => $mapping->table_number,
            'customer_name' => $order->customer_name,
            'priority' => TableNotification::PRIORITY_HIGH,
            'recipient_type' => TableNotification::RECIPIENT_EMPLOYEE,
            'data' => [
                'area' => $mapping->area,
                'submit_time' => now()->toISOString()
            ]
        ]);
    }

    /**
     * Create notifications for manual mapping
     */
    private function createNotificationsForManualMapping(TableMapping $mapping, TableOrder $order)
    {
        $adminName = auth()->user()->name ?? 'Admin';

        // Employee notification
        TableNotification::create([
            'type' => TableNotification::TYPE_NEW_ORDER,
            'title' => 'New Table Assignment',
            'message' => "Order #{$order->order_number} has been assigned to Table {$mapping->table_number}",
            'order_number' => $order->order_number,
            'table_number' => $mapping->table_number,
            'customer_name' => $order->customer_name,
            'priority' => TableNotification::PRIORITY_HIGH,
            'recipient_type' => TableNotification::RECIPIENT_EMPLOYEE,
            'data' => [
                'source' => 'admin',
                'area' => $mapping->area,
                'assigned_by' => $adminName
            ]
        ]);
    }

    /**
     * Create notifications for status updates
     */
    private function createNotificationsForStatusUpdate(TableOrder $order, $mapping, $oldStatus, $newStatus)
    {
        $statusMessages = [
            'pending' => 'Order is pending',
            'preparing' => 'Order is being prepared',
            'ready' => 'Order is ready for delivery',
            'delivered' => 'Order has been delivered',
            'completed' => 'Order completed'
        ];

        $priority = $newStatus === 'ready' ? TableNotification::PRIORITY_HIGH : TableNotification::PRIORITY_MEDIUM;

        // Employee notification
        TableNotification::create([
            'type' => TableNotification::TYPE_ORDER_UPDATED,
            'title' => 'Order Status Updated',
            'message' => "Order #{$order->order_number}" . 
                        ($mapping ? " at Table {$mapping->table_number}" : '') . 
                        ": {$statusMessages[$newStatus]}",
            'order_number' => $order->order_number,
            'table_number' => $mapping ? $mapping->table_number : null,
            'customer_name' => $order->customer_name,
            'priority' => $priority,
            'recipient_type' => TableNotification::RECIPIENT_EMPLOYEE,
            'data' => [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'updated_by' => auth()->user()->name ?? 'Admin'
            ]
        ]);
    }

    /**
     * Create notifications for delivery
     */
    private function createNotificationsForDelivery(TableOrder $order, TableMapping $mapping)
    {
        // Employee notification
        TableNotification::create([
            'type' => TableNotification::TYPE_ORDER_DELIVERED,
            'title' => 'Order Delivered',
            'message' => "Order #{$order->order_number} successfully delivered to Table {$mapping->table_number}",
            'order_number' => $order->order_number,
            'table_number' => $mapping->table_number,
            'customer_name' => $order->customer_name,
            'priority' => TableNotification::PRIORITY_LOW,
            'recipient_type' => TableNotification::RECIPIENT_EMPLOYEE,
            'data' => [
                'delivered_by' => $mapping->delivered_by,
                'delivery_time' => $mapping->delivered_at->toISOString()
            ]
        ]);
    }

    /**
     * Update order status (Employee only)
     */
    public function updateEmployeeOrderStatus(Request $request, $orderNumber): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:' . implode(',', TableOrder::getValidStatuses()),
            'mapping_id' => 'nullable|integer',
            'table_number' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Find specific order by mapping_id first, then by order_number + table_number
            $order = null;
            
            if ($request->mapping_id) {
                $order = TableOrder::where('mapping_id', $request->mapping_id)->first();
            } elseif ($request->table_number) {
                $order = TableOrder::where('order_number', $orderNumber)
                    ->where('table_number', $request->table_number)
                    ->first();
            } else {
                // Fallback to first order with this number
                $order = TableOrder::where('order_number', $orderNumber)->first();
            }
            
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found for the specified criteria'
                ], 404);
            }

            $oldStatus = $order->status;
            $order->update(['status' => $request->status]);

            // Get the associated mapping
            $mapping = TableMapping::where('id', $order->mapping_id)->first();

            // Handle delivered status - also mark mapping as delivered
            if ($request->status === TableOrder::STATUS_DELIVERED && $mapping && $mapping->status === TableMapping::STATUS_ACTIVE) {
                $mapping->update([
                    'status' => TableMapping::STATUS_DELIVERED,
                    'delivered_at' => now(),
                    'delivered_by' => auth()->user()->name ?? 'Employee'
                ]);
            }

            // Create status update notifications
            $this->createNotificationsForStatusUpdate($order, $mapping, $oldStatus, $request->status);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order status updated successfully',
                'data' => [
                    'order' => $order->fresh(),
                    'mapping' => $mapping ? $mapping->fresh() : null
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update order status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark order as delivered (Employee only)
     */
    public function markEmployeeDelivered(Request $request, $orderNumber): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'delivered_by' => 'nullable|string|max:255',
            'mapping_id' => 'nullable|integer',
            'table_number' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Find specific order by mapping_id first, then by order_number + table_number
            $order = null;
            
            if ($request->mapping_id) {
                $order = TableOrder::where('mapping_id', $request->mapping_id)->first();
            } elseif ($request->table_number) {
                $order = TableOrder::where('order_number', $orderNumber)
                    ->where('table_number', $request->table_number)
                    ->first();
            } else {
                // Fallback to first order with this number
                $order = TableOrder::where('order_number', $orderNumber)->first();
            }
            
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found for the specified criteria'
                ], 404);
            }

            $mapping = $order->mapping;
            if (!$mapping) {
                return response()->json([
                    'success' => false,
                    'message' => 'Table mapping not found for this order'
                ], 404);
            }

            // Update order and mapping
            $order->update(['status' => TableOrder::STATUS_DELIVERED]);
            
            $mapping->update([
                'status' => TableMapping::STATUS_DELIVERED,
                'delivered_at' => now(),
                'delivered_by' => $request->delivered_by ?: (auth()->user()->name ?? 'Employee')
            ]);

            // Create delivery notifications
            $this->createNotificationsForDelivery($order, $mapping);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order marked as delivered successfully',
                'data' => [
                    'order' => $order->fresh(),
                    'mapping' => $mapping->fresh()
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark order as delivered',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
