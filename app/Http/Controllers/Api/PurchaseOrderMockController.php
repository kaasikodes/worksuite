<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Traits\ApiResponseTrait;


class PurchaseOrderMockController extends Controller
{
    use ApiResponseTrait;

    public function index()
    {
        // TODO: Response ought to be paginated
        return $this->successResponse('Purchase Orders retrieved successfully.', PurchaseOrder::all());
    }
}
