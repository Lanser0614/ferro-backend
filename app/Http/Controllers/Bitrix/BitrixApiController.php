<?php

namespace App\Http\Controllers\Bitrix;

use App\Http\Controllers\Controller;
use App\UseCase\Bitrix\SupCreateOrderUseCase;
use App\UseCase\Bitrix\SyncOrderFromSupToBitrixUseCase;
use Illuminate\Http\Client\RequestException;

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
    public function createOrderToSup(int $dealId, SupCreateOrderUseCase $createOrderUseCase): void
    {
        $createOrderUseCase->execute($dealId);
    }
}
