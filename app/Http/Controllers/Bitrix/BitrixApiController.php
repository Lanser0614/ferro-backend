<?php

namespace App\Http\Controllers\Bitrix;

use App\Http\Controllers\Controller;
use App\UseCase\Bitrix\SupCreateOrderUseCase;
use App\UseCase\Bitrix\SyncOrderFromSupToBitrixUseCase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BitrixApiController extends Controller
{
    public function __construct()
    {
    }

    /**
     * @param int $contactId
     * @param SyncOrderFromSupToBitrixUseCase $syncOrderFromSupToBitrixUseCase
     * @throws RequestException
     */
    public function syncContactAndSupOrders(int $contactId, SyncOrderFromSupToBitrixUseCase $syncOrderFromSupToBitrixUseCase): void
    {
        $syncOrderFromSupToBitrixUseCase->execute($contactId);
    }


    /**
     * @param int $dealId
     * @param SupCreateOrderUseCase $createOrderUseCase
     * @return void
     */
    public function createOrderToSup(Request $request, SupCreateOrderUseCase $createOrderUseCase): void
    {
        Log::info('bitrix-webhook', $request->all());
//        $createOrderUseCase->execute($dealId);
    }
}
