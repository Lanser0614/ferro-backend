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
});

Route::any('/contact/history/order/{contactId}', [BitrixApiController::class, 'syncContactAndSupOrders']);
Route::any('/sup/orders/create', [BitrixApiController::class, 'createOrderToSup']);
