<?php

use App\Http\Controllers\Bitrix\BitrixApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider, and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::any('/test', function (Request $request) {
    $startMemory = memory_get_usage(true); // bytes, really allocated

    $startTime = now();
    $eventType = request()->input('0.eventType');
    $iikoStatus = request()->input('0.eventInfo.order.status');
//    $eventType = data_get($data, '0.eventType');
//    $iikoStatus = data_get($data, '0.eventInfo.order.status');
//
//     Carbon::parse(data_get($data, '0.eventInfo.order.whenDelivered'));
    Carbon::parse(request()->input('0.eventInfo.order.whenDelivered'));

    $endMemory = memory_get_usage(true);
    $peakMemory = memory_get_peak_usage(true);


    dd([
        'time_microseconds' => now()->diffInMicroseconds($startTime),
        'memory_used_mb' => round(($endMemory - $startMemory) / 1024 / 1024, 3),
        'memory_peak_mb' => round($peakMemory / 1024 / 1024, 3),
    ]);//    Log::info('data', $request->all());
});

Route::any('/contact/history/order/{contactId}', [BitrixApiController::class, 'syncContactAndSupOrders']);
Route::any('/sup/orders/create', [BitrixApiController::class, 'createOrderToSup']);
