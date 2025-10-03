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

echo "=== DATABASE CHECK ===" . PHP_EOL;
echo "Orders: " . Order::count() . PHP_EOL;
echo "Table Mappings: " . TableMapping::count() . PHP_EOL;

if (TableMapping::count() > 0) {
    echo PHP_EOL . "Table Mappings:" . PHP_EOL;
    foreach (TableMapping::all() as $tm) {
        echo "- ID: {$tm->id}, Table: {$tm->table_number}, Status: {$tm->status}" . PHP_EOL;
    }
}

if (Order::count() > 0) {
    echo PHP_EOL . "Orders:" . PHP_EOL;
    foreach (Order::all() as $order) {
        echo "- ID: {$order->id}, Table: {$order->table_number}, Status: {$order->status}" . PHP_EOL;
    }
}
echo "======================" . PHP_EOL;
?>
