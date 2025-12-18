<?php

namespace App\UseCase\Bitrix;

use App\BitrixManager\Bitrix;
use App\Enum\BitrixDealStageIdEnum;
use App\Models\SupOrder;
use App\Services\Http\FerroSiteBackEndHttpService;
use App\Services\Http\SupCrmApiOrderClientHttpService;
use Doniyor\Bitrix24\Bitrix24Manager;
use Doniyor\Bitrix24\CRM\Mappers\DealResponseMapper;
use Illuminate\Support\Facades\Log;

class SupCreateOrderUseCase
{
    public function __construct(
        private readonly Bitrix                 $bitrix24Manager,
        private readonly FerroSiteBackEndHttpService     $ferroSiteBackEndHttpService,
        private readonly SupCrmApiOrderClientHttpService $supHttpClientService,
        private readonly DealResponseMapper $dealResponseMapper
    ) {}

    public function execute(int $dealId): array
    {
        $deal = $this->bitrix24Manager->sendDataToBitrix('crm.deal.get', [
            'id' => $dealId,
        ]);

        $dealDto = $this->dealResponseMapper->map($deal);

        if ($dealDto->stageId != BitrixDealStageIdEnum::EXECUTING->value) {
            Log::debug('create-deal-on-sup', [
                'dealId' => $dealId,
                'sapId'  => '',
                'status' => 'skipped',
            ]);

            return [
                'dealId' => $dealId,
                'sapId'  => '',
                'status' => 'skipped',
            ];
        }

        // SAP ID уже есть → просто возвращаем сделку
        if (!empty($dealDto->extra()['UF_CRM_1765651317145'])) {
            Log::debug('create-deal-on-sup', [
                'dealId' => $dealId,
                'sapId'  => $dealDto->extra()['UF_CRM_1765651317145'],
                'status' => 'already-synced',
            ]);

            return $deal;
        }



        // Нет origin_id → нечего синкать
        if (empty($dealDto->extra()['ORIGIN_ID'])) {
            throw new \DomainException('Deal does not contain ORIGIN_ID');
        }

        $supOrder = SupOrder::query()->where('bitrix_deal_id', '=', $dealDto->id)->first();

        if ($supOrder) {
            Log::debug('create-deal-on-sup', [
                'dealId' => $dealId,
                'sapId'  => $supOrder->sup_order_id,
                'status' => 'already-synced',
            ]);

            if ($supOrder->sup_order_id != $dealDto->extra()['UF_CRM_1765651317145']) {
                $this->bitrix24Manager->sendDataToBitrix(
                    'crm.deal.update',
                    [
                        'id' => $dealId,
                        'fields' => [
                            'UF_CRM_1765651317145' => $supOrder->sup_order_id,
                        ],
                    ]
                );
            }

            return $deal;
        }

        $ferroOrderId = $dealDto->extra()['ORIGIN_ID'];

        $ferroOrder = $this->ferroSiteBackEndHttpService
            ->serviceAccountOrderById($ferroOrderId);

        $mappedOrder = $this->ferroSiteBackEndHttpService
            ->mapOrderToSup($ferroOrder);

        $sapResponse = $this->supHttpClientService
            ->createOrder($mappedOrder);

        $sapId = (string) $sapResponse['id'];

        // Обновляем сделку в Bitrix
        $this->bitrix24Manager->sendDataToBitrix(
            'crm.deal.update',
            [
                'id' => $dealId,
                'fields' => [
                    'UF_CRM_1765651317145' => $sapId,
                ],
            ]
        );

        $newSupOrder = new SupOrder();
        $newSupOrder->sup_order_id = (int) $sapId;
        $newSupOrder->bitrix_deal_id = $dealId;
        $newSupOrder->save();

        Log::debug('create-deal-on-sup', [
            'dealId' => $dealId,
            'sapId'  => $sapId,
            'status' => 'synced',
        ]);

        return [
            'dealId' => $dealId,
            'sapId'  => $sapId,
            'status' => 'synced',
        ];
    }
}
