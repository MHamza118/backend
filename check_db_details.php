<?php
require_once 'vendor/autoload.php';

use App\Models\Order;
use App\Models\TableMapping;
use Illuminate\Foundation\Application;
use Illuminate\Contracts\Console\Kernel;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

echo "=== DETAILED DATABASE CHECK ===" . PHP_EOL;

echo PHP_EOL . "=== TABLE ORDERS ===" . PHP_EOL;
foreach (Order::all() as $order) {
    echo "Order ID: {$order->id}, Order Number: {$order->order_number}, Customer: {$order->customer_name}, Status: {$order->status}, Created: {$order->created_at}" . PHP_EOL;
}

echo PHP_EOL . "=== TABLE MAPPINGS ===" . PHP_EOL;
foreach (TableMapping::orderBy('order_number')->orderBy('created_at')->get() as $mapping) {
    $submissionId = $mapping->submission_id ? substr($mapping->submission_id, -10) : 'legacy';
    echo "Mapping ID: {$mapping->id}, Order: #{$mapping->order_number}, Table: {$mapping->table_number}, Area: {$mapping->area}, Status: {$mapping->status}, Source: {$mapping->source}, SubmissionID: {$submissionId}, Created: {$mapping->created_at}" . PHP_EOL;
}

echo PHP_EOL . "=== NOTIFICATIONS ===" . PHP_EOL;
$notificationModel = 'App\\Models\\TableNotification';
if (class_exists($notificationModel)) {
    $notifications = $notificationModel::all();
    echo "Count: " . $notifications->count() . PHP_EOL;
    foreach ($notifications as $notification) {
        echo "Notification ID: {$notification->id}, Type: {$notification->type}, Order: {$notification->order_number}, Title: {$notification->title}" . PHP_EOL;
    }
} else {
    echo "TableNotification model not found" . PHP_EOL;
}

echo PHP_EOL . "=== ANALYSIS ===" . PHP_EOL;
$orderGroups = TableMapping::where('status', 'active')
    ->groupBy('order_number')
    ->selectRaw('order_number, count(*) as mapping_count')
    ->having('mapping_count', '>', 1)
    ->get();

if ($orderGroups->count() > 0) {
    echo "Orders with multiple active table mappings:" . PHP_EOL;
    foreach ($orderGroups as $group) {
        echo "- Order #{$group->order_number}: {$group->mapping_count} tables" . PHP_EOL;
        $mappings = TableMapping::where('order_number', $group->order_number)
            ->where('status', 'active')
            ->get(['table_number', 'area', 'created_at']);
        foreach ($mappings as $mapping) {
            echo "  • Table {$mapping->table_number} ({$mapping->area}) - {$mapping->created_at}" . PHP_EOL;
        }
    }
} else {
    echo "No orders with multiple active mappings found." . PHP_EOL;
}

echo "=================================" . PHP_EOL;
?>
