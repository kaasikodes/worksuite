<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class AiMockController extends Controller
{
    public function extract(Request $request)
    {
        return response()->json([
            'invoice_number' => 'INV-' . rand(1000, 9999),
            'total_amount' => rand(100, 1000) . '.00',
            'vendor' => 'Vendor ' . rand(1, 5),
        ]);
    }
}
