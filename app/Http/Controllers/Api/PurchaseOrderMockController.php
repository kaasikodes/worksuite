<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;

class PurchaseOrderMockController extends Controller
{
    public function index()
    {
        return response()->json(PurchaseOrder::all());
    }
}
