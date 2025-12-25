<?php

namespace App\UseCase\Bitrix;

use App\BitrixManager\Bitrix;
use App\Models\FerroSupOrder;
use App\Services\Http\SupCrmApiOrderClientHttpService;
use Doniyor\Bitrix24\Bitrix24Manager;
use Doniyor\Bitrix24\CRM\Mappers\ContactResponseMapper;
use Doniyor\Bitrix24\Exceptions\Bitrix24RequestException;
use Illuminate\Http\Client\RequestException;

class SyncOrderFromSupToBitrixUseCase
{
    public function __construct(
        private readonly Bitrix                          $bitrixManager,
        private readonly ContactResponseMapper           $contactResponseMapper,
        private readonly SupCrmApiOrderClientHttpService $ferroOrderClientHttpService
    )
    {
    }

    /**
     * @throws RequestException
     */
    public function execute(int $contactId): void
    {

        $contact = $this->bitrixManager->sendDataToBitrix('crm.contact.get', [
            'id' => $contactId,
        ]);

        try {
            $contact = $this->contactResponseMapper->map($contact);
        } catch (Bitrix24RequestException $th) {
            return;
        }


        $sapContactId = $contact->extra()['UF_CRM_1763806272'];

        if (!$sapContactId) {
            return;
        }

        $debts = $this->ferroOrderClientHttpService->getClientDebtByBusinessPartnerId($sapContactId);
        $contactExtraFields = array_merge(
            $this->prepareContactExtraFields($sapContactId),
            $this->mapDebtFields($debts)
        );

        if (!empty($contactExtraFields)) {
            $this->bitrixManager->sendDataToBitrix('crm.contact.update', [
                'ID' => $contact->id,
                'fields' => $contactExtraFields,
            ]);
        }

        $supOrders = FerroSupOrder::whereBitrixContactId($contact->id)
            ->pluck('sup_order_id')
            ->toArray();

        try {
            $orders = $this->ferroOrderClientHttpService->listOrders($sapContactId);
        } catch (RequestException $exception) {
            return;
        }

        if (data_get($orders, 'totalCount') == 0) {
            return;
        }

        $collectOrders = collect(data_get($orders, 'result', []))
            ->whereNotIn('id', $supOrders);

        foreach ($collectOrders as $order) {

            $orderInfo = $this->mapSapToBitrix($order);

            $this->bitrixManager->sendDataToBitrix('crm.timeline.comment.add', [
                'fields' => [
                    'ENTITY_TYPE' => 'contact',
                    'ENTITY_ID' => $contact->id,
                    'COMMENT' => $orderInfo
                ],
            ]);
            $model = new FerroSupOrder();
            $model->bitrix_contact_id = $contact->id;
            $model->sup_order_id = $order['id'];
            $model->contact_sup_id = $sapContactId;
            $model->save();
        }


    }


    private function mapSapToBitrix(array $sap): string
    {
        // Собрать items как текст для ленты (по твоей таблице "items → Комментарий ленты Bitrix24")
        $itemsComment = "";
        if (!empty($sap['items']) && is_array($sap['items'])) {
            foreach ($sap['items'] as $item) {
                $itemsComment .= sprintf(
                    "%s × %s, price: %s\n",
                    $item['productName'] ?? '',
                    $item['qty'] ?? '',
                    $item['price'] ?? ''
                );
            }
        }

        return sprintf(
            "ID заказа: %s\n" .
            "Клиент id: %s\n" .
            "Имя контакта: %s\n" .
            "ID ответственного: %s\n" .
            "Ответственный за сделку: %s\n" .
            "Дата создания: %s\n" .
            "Дата изменения: %s\n" .
            "Сумма: %s\n" .
            "Комментарий: %s\n" .
            "Количество: %s\n" .
            "Тип заказа: %s\n" .
            "Продукты:\n%s",
            $sap['id'] ?? '',
            $sap['businessPartnerId'] ?? '',
            $sap['businessPartnerName'] ?? '',
            $sap['salesPersonId'] ?? '',
            $sap['salesPersonName'] ?? '',
            $sap['createDate'] ?? '',
            $sap['updateDate'] ?? '',
            $sap['total'] ?? '',
            $sap['comments'] ?? '',
            $sap['itemsCount'] ?? '',
            $sap['orderType'] ?? '',
            trim($itemsComment)
        );
    }

    private function prepareContactExtraFields(string $sapContactId): array
    {
        try {
            $customer = $this->ferroOrderClientHttpService->getCustomerById($sapContactId);
        } catch (RequestException $exception) {
            return [];
        }

        if (data_get($customer, 'deleted') === true) {
            return [];
        }

        $address = $this->resolveCustomerAddress($customer['addresses'] ?? []);

        $groupId = data_get($customer, 'groupId');
        $groupId = is_numeric($groupId) ? (int) $groupId : null;

        $fields = [
            'UF_CRM_1761817021' => data_get($customer, 'segment') ?? data_get($customer, 'segmentName'),
            'UF_CRM_1761817826' => $this->resolveLocation($address),
            'UF_CRM_1766518923' => $this->ferroOrderClientHttpService->findSalesPointByCustomerGroupId($groupId),
            'UF_CRM_1766518953' => data_get($address, 'county'),
        ];

        return array_filter($fields, static fn($value) => $value !== null && $value !== '');
    }

    private function mapDebtFields(array $debts): array
    {
        $debtCollection = collect($debts)->map(function ($debt) {
            return [
                'balance' => data_get($debt, 'balance'),
                'debtBalance' => (float) data_get($debt, 'debit', 0) - (float) data_get($debt, 'credit', 0),
                'overdueInDays' => data_get($debt, 'overdueInDays'),
                'dueDate' => data_get($debt, 'dueDate'),
                'documentId' => data_get($debt, 'documentId'),
                'documentTypeCode' => data_get($debt, 'documentTypeCode'),
            ];
        });

        if ($debtCollection->isEmpty()) {
            return [];
        }

        return [
            'UF_CRM_1766517091' => $debtCollection->pluck('balance')->toArray(),
            'UF_CRM_1766517351' => $debtCollection->pluck('debtBalance')->toArray(),
            'UF_CRM_1766517429' => $debtCollection->pluck('overdueInDays')->toArray(),
            'UF_CRM_1766517483' => $debtCollection->pluck('dueDate')->toArray(),
            'UF_CRM_1766517544' => $debtCollection->pluck('documentId')->toArray(),
            'UF_CRM_1766517575' => $debtCollection->pluck('documentTypeCode')->toArray(),
        ];
    }

    private function resolveCustomerAddress(array $addresses): array
    {
        $shipping = collect($addresses)->first(function (array $address) {
            return strtoupper((string) data_get($address, 'type')) === 'SHIPPING';
        });

        if (!empty($shipping)) {
            return $shipping;
        }

        $billing = collect($addresses)->first(function (array $address) {
            return strtoupper((string) data_get($address, 'type')) === 'BILLING';
        });

        if (!empty($billing)) {
            return $billing;
        }

        return $addresses[0] ?? [];
    }

    private function resolveLocation(?array $address): ?string
    {
        if (empty($address)) {
            return null;
        }

        if (!empty(data_get($address, 'buildingFloorRoom'))) {
            return data_get($address, 'buildingFloorRoom');
        }

        $latitude = data_get($address, 'latitude');
        $longitude = data_get($address, 'longitude');

        if ($latitude !== null && $longitude !== null) {
            return sprintf('%s,%s', $latitude, $longitude);
        }

        if (!empty(data_get($address, 'address'))) {
            return data_get($address, 'address');
        }

        return null;
    }
}
