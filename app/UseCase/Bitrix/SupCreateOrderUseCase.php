<?php

namespace App\UseCase\Bitrix;

use App\Services\Http\FerroSiteBackEndHttpService;
use App\Services\Http\SupCrmApiOrderClientHttpService;
use Doniyor\Bitrix24\Bitrix24Manager;

class SupCreateOrderUseCase
{
    public function __construct(
        private readonly Bitrix24Manager                 $bitrix24Manager,
        private readonly FerroSiteBackEndHttpService     $ferroSiteBackEndHttpService,
        private readonly SupCrmApiOrderClientHttpService $supHttpClientService,
    ) {}

    public function execute(int $dealId): array
    {
        $deal = $this->bitrix24Manager
            ->crm()
            ->deals()
            ->get($dealId);

        // SAP ID уже есть → просто возвращаем сделку
        if (!empty($deal['UF_CRM_1765651317145'])) {
            return $deal;
        }

        // Нет origin_id → нечего синкать
        if (empty($deal['ORIGIN_ID'])) {
            throw new \DomainException('Deal does not contain ORIGIN_ID');
        }

        $ferroOrderId = $deal['ORIGIN_ID'];

        $ferroOrder = $this->ferroSiteBackEndHttpService
            ->serviceAccountOrderById($ferroOrderId);

        $mappedOrder = $this->ferroSiteBackEndHttpService
            ->mapOrderToSup($ferroOrder);

        $sapResponse = $this->supHttpClientService
            ->createOrder($mappedOrder);

        $sapId = (string) $sapResponse['id'];

        // Обновляем сделку в Bitrix
        $this->bitrix24Manager->call(
            'crm.deal.update',
            [
                'id' => $dealId,
                'fields' => [
                    'UF_CRM_1765651317145' => $sapId,
                ],
            ]
        );

        return [
            'dealId' => $dealId,
            'sapId'  => $sapId,
            'status' => 'synced',
        ];
    }
}
