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
        private readonly Bitrix                      $bitrixManager,
        private readonly ContactResponseMapper                $contactResponseMapper,
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

        $debts = $this->ferroOrderClientHttpService->getClientDebtByBusinessPartnerId('12-ABDULLOH29');
        $debtCollection = collect($debts)->map(function ($debt) {
            return [
                'balance' => $debt['balance'],
                'debtBalance' => $debt['debit'] - $debt['credit'],
                'overdueInDays' => $debt['overdueInDays'],
                'dueDate' => $debt['dueDate'],
                'documentId' => $debt['documentId'],
                'documentTypeCode' => $debt['documentTypeCode'],
            ];
        });


        $supOrders = FerroSupOrder::whereBitrixContactId($contact->id)
            ->pluck('sup_order_id')->toArray();;

        $orders = $this->ferroOrderClientHttpService->listOrders($sapContactId);

        $debts = $this->ferroOrderClientHttpService->getClientDebtByBusinessPartnerId($sapContactId);

        if (count($debts) > 0) {
            $this->bitrixManager->sendDataToBitrix('crm.contact.update', [
                'ID' => $contact->id,
                'fields' => [

                ]
            ]);
        }

        if ($orders['totalCount'] == 0) {
            return;
        }

        $collectOrders = collect($orders['result'])->whereNotIn('id', $supOrders);;

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
}
