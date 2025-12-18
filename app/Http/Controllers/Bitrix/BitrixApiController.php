<?php

namespace App\Http\Controllers\Bitrix;

use App\Http\Controllers\Controller;
use App\UseCase\Bitrix\SupCreateOrderUseCase;
use App\UseCase\Bitrix\SyncOrderFromSupToBitrixUseCase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
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
     * @param Request $request
     * @param SupCreateOrderUseCase $createOrderUseCase
     * @return JsonResponse
     */
    public function createOrderToSup(Request $request, SupCreateOrderUseCase $createOrderUseCase): JsonResponse
    {
        Log::info('bitrix-webhook', $request->all());
        $dealId = data_get($request->all(), 'data.FIELDS.ID');
        return new JsonResponse($createOrderUseCase->execute((int)$dealId));
    }
}
