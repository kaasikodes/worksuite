<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PurchaseOrder;

class PurchaseOrderSeeder extends Seeder
{
    public function run(): void
    {
        $orders = [
            ['po_number' => 'PO-1001', 'vendor' => 'Vendor 1', 'amount' => 500.00],
            ['po_number' => 'PO-1002', 'vendor' => 'Vendor 3', 'amount' => 250.00],
            ['po_number' => 'PO-1003', 'vendor' => 'Vendor 5', 'amount' => 1200.00],
            ['po_number' => 'PO-1004', 'vendor' => 'Vendor 5', 'amount' => 584.00],
        ];

        foreach ($orders as $order) {
            PurchaseOrder::create($order);
        }
    }
}
