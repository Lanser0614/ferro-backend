<?php

namespace App\UseCase\Bitrix;

use App\Models\FerroProducts;
use App\Services\Http\FerroSiteBackEndHttpService;
use Doniyor\Bitrix24\Bitrix24Manager;
use Doniyor\Bitrix24\CRM\Mappers\ContactResponseMapper;
use Doniyor\Bitrix24\DTO\CRM\ContactFieldsDto;
use Doniyor\Bitrix24\DTO\CRM\DealFieldsDto;

class BitrixOrderSyncUseCase
{
    public function __construct(
        private readonly FerroSiteBackEndHttpService $backendService,
        private readonly Bitrix24Manager             $bitrix,
        private readonly ContactResponseMapper       $contactMapper
    )
    {
    }

    /**
     * Главная операция: синхронизация всех заказов → контакты → сделки.
     * @param array $order
     * @return array
     */
    public function sync(array $order): array
    {
        // 1) Контакт
        $contact = $this->findOrCreateContact($order);
        $contactDto = $this->contactMapper->map($contact);

        // 2) Если сделка есть → пропускаем
        if ($this->dealExists($order['id'])) {
            $dealId = $this->bitrix->crm()->deals()->list(
                filter: ['ORIGIN_ID' => $order['id']],
                select: ['ID']
            )['result'][0]['ID'];
        } else {
            // 3) Создаём сделку
            $dealId = $this->createDeal($contactDto, $order);
        }

        $this->insertProducts($order, $dealId);


        return ['status' => 'OK'];
    }

    /**
     * Поиск + создание контакта
     */
    private function findOrCreateContact(array $order): array
    {
        $customerId = $order['customerExternalId'];

        $contacts = $this->findContactsByCustomerId($customerId);

        if ($contacts['total'] == 0) {
            $contactId = $this->createContact($order);
            return $this->bitrix->crm()->contacts()->get($contactId);
        }

        if ($contacts['total'] > 1) {
            $contact = collect($contacts['result'])
                ->where('UF_CRM_1763806272', $customerId)
                ->first();

            if ($contact) {
                return $contact;
            }

            $contactId = $this->createContact($order);
            return $this->bitrix->crm()->contacts()->get($contactId);
        }

        return $contacts['result'][0];
    }

    /**
     * Запрос списка контактов по кастомному UF-полю
     */
    private function findContactsByCustomerId(string $customerId): array
    {
        return $this->bitrix->crm()->contacts()->list(
            filter: ['UF_CRM_1763806272' => $customerId],
            select: ['ID', 'NAME', 'UF_CRM_1763806272']
        );
    }

    /**
     * Создание контакта в Bitrix
     */
    private function createContact(array $order): int
    {
        return $this->bitrix->crm()->contacts()->add(
            new ContactFieldsDto(
                name: $order['customerName'],
                phones: [
                    [
                        'VALUE' => $order['customerPhone'],
                        'VALUE_TYPE' => 'MOBILE'
                    ],
                ],
                extra: [
                    'UF_CRM_1763806272' => $order['customerExternalId']
                ]
            )
        );
    }

    /**
     * Проверка, существует ли сделка по Origin Id
     */
    private function dealExists(string $originId): bool
    {
        $deal = $this->bitrix->crm()->deals()->list(
            filter: ['ORIGIN_ID' => $originId],
            select: ['ID']
        );

        return $deal['total'] > 0;
    }

    /**
     * Создание сделки
     */
    private function createDeal(object $contactDto, array $order): int
    {
        return $this->bitrix->crm()->deals()->add(
            new DealFieldsDto(
                title: 'Заказ: ' . $contactDto->name,
                categoryId: '0',
                stageId: '52',
                contactId: $contactDto->id,
                extra: [
                    'OPPORTUNITY' => $order['total'],
                    'CURRENCY_ID' => 'USD',
                    'UF_CRM_1763822803420' => $order['sourceType'],
                    'UF_CRM_1763823006332' => $order['status'],
                    'UF_CRM_1763823082918' => $order['currencyRate'],
                    'DATE_CREATE' => $order['createdDate'],
                    'ORIGIN_ID' => $order['id']
                ]
            )
        );
    }


    public function insertProducts(array $order, int $dealId): void
    {
        $orderData = $this->backendService->serviceAccountOrderById($order['id']);
        $orderProducts = $orderData['products'];

        $existDealProducts = $this->bitrix->call('crm.item.productrow.list', [
            'filter' => [
                "=ownerType" => 'D',
                "=ownerId" => $dealId,
            ],
        ]);

        if (isset($existDealProducts['result']['productRows'])) {
            $dealProductIds = collect($existDealProducts['result']['productRows'])->pluck('id')->toArray();

            foreach ($dealProductIds as $id) {
                $this->bitrix->call('crm.item.productrow.delete', [
                    'id' => $id,
                ]);
            }
        }

        foreach ($orderProducts as $orderProduct) {
            $orderId = $order['id'];
            $qty = $orderProduct['quantity'];
            $productPrice = $orderProduct['productPrice'];
            $whsId = $orderProduct['whsCode'];
            $productSupId = $orderProduct['product']['externalId'];
            $productName = $orderProduct['product']['searchableName'];

            $bitrixProduct = $this->bitrix->call('catalog.product.list', [
                "select" => ["id", 'iblockId', 'name', 'xmlId'],
                "filter" => [
                    "xmlId" => $productSupId,
                    "iblockId" => FerroProducts::CATALOG_ID
                ]
            ]);

            if (isset($bitrixProduct['result']['products'])) {
                $product = collect($bitrixProduct['result']['products'])->where('xmlId', '=', $productSupId)->first();
            }

            if ($product) {
                $bitrixProductId = $product['id'];
            } else {
                try {
                    $productData = $this->bitrix->call('catalog.product.add', [
                        "fields" => [
                            'iblockId' => FerroProducts::CATALOG_ID,
                            "name" => $productName,
                            'xmlId' => $productSupId,
                            'code' => $whsId
                        ]
                    ]);
                } catch (\Exception $e) {
                    continue;
                }
                $bitrixProductId = $productData['result']['element']['id'];
            }

            $this->bitrix->call('crm.item.productrow.add', [
                "fields" => [
                    'ownerId' => $dealId,
                    'ownerType' => 'D',
                    'productId' => $bitrixProductId,
                    'quantity' => $qty,
                    'price' => $productPrice,
                ]
            ]);


        }
    }
}
