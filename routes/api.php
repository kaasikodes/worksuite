<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AiMockController;
use App\Http\Controllers\Api\PurchaseOrderMockController;

Route::post('/mock-ai-extract', [AiMockController::class, 'extract']);
Route::get('/mock-purchase-orders', [PurchaseOrderMockController::class, 'index']);