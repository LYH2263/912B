<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BundleApiController;
use App\Http\Controllers\Api\DashboardApiController;
use App\Http\Controllers\Api\FlashSaleApiController;
use App\Http\Controllers\Api\InventoryApiController;
use App\Http\Controllers\Api\MemberApiController;
use App\Http\Controllers\Api\OrderApiController;
use App\Http\Controllers\Api\PricingRuleApiController;
use App\Http\Controllers\Api\ProductApiController;
use App\Http\Controllers\Api\PurchaseOrderApiController;
use App\Http\Controllers\Api\TicketApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('api')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);

        Route::apiResource('products', ProductApiController::class);
        Route::get('products/{product}/inventory', [ProductApiController::class, 'show']);

        Route::apiResource('bundles', BundleApiController::class);
        Route::put('bundles/{bundle}/toggle', [BundleApiController::class, 'toggleActive']);

        Route::apiResource('orders', OrderApiController::class);
        Route::put('orders/{order}/status', [OrderApiController::class, 'updateStatus']);
        Route::post('orders/{order}/split', [OrderApiController::class, 'split']);
        Route::post('orders/merge', [OrderApiController::class, 'merge']);
        Route::get('orders/merge-candidates', [OrderApiController::class, 'mergeCandidates']);
        Route::get('orders/statistics', [OrderApiController::class, 'index']);

        Route::get('inventory', [InventoryApiController::class, 'index']);
        Route::get('inventory/statistics', [InventoryApiController::class, 'index']);
        Route::get('inventory/{product}', [InventoryApiController::class, 'show']);
        Route::put('inventory/{product}', [InventoryApiController::class, 'update']);

        Route::get('dashboard/summary', [DashboardApiController::class, 'summary']);
        Route::get('dashboard/charts', [DashboardApiController::class, 'charts']);

        Route::apiResource('flash-sales', FlashSaleApiController::class);
        Route::get('flash-sales-active', [FlashSaleApiController::class, 'activeList']);
        Route::post('flash-sales/{flashSale}/order', [FlashSaleApiController::class, 'placeOrder']);

        Route::get('member', [MemberApiController::class, 'me']);
        Route::get('member/point-logs', [MemberApiController::class, 'pointLogs']);
        Route::post('member/calculate-discount', [MemberApiController::class, 'calculateDiscount']);

        Route::apiResource('purchase-orders', PurchaseOrderApiController::class);
        Route::put('purchase-orders/{purchaseOrder}/submit', [PurchaseOrderApiController::class, 'submit']);
        Route::post('purchase-orders/{purchaseOrder}/stock-in', [PurchaseOrderApiController::class, 'stockIn']);

        Route::apiResource('pricing-rules', PricingRuleApiController::class);
        Route::put('pricing-rules/{pricingRule}/toggle', [PricingRuleApiController::class, 'toggleActive']);

        Route::get('tickets-kanban', [TicketApiController::class, 'kanban']);
        Route::get('tickets-assignees', [TicketApiController::class, 'assignees']);
        Route::get('tickets-counts', [TicketApiController::class, 'counts']);
        Route::apiResource('tickets', TicketApiController::class);
        Route::put('tickets/{ticket}/status', [TicketApiController::class, 'updateStatus']);
        Route::put('tickets/{ticket}/assign', [TicketApiController::class, 'assign']);
        Route::post('tickets/{ticket}/comments', [TicketApiController::class, 'addComment']);
    });
});
