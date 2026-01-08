<?php

namespace App\Console\Commands;

use App\BitrixManager\Bitrix;
use App\Services\Http\FerroSiteBackEndHttpService;
use App\Services\Http\SupCrmApiOrderClientHttpService;
use Doniyor\Bitrix24\CRM\Mappers\ContactResponseMapper;
use Doniyor\Bitrix24\DTO\CRM\ContactFieldsDto;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

class SyncContactFromSupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-contact-from-sup-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(
        SupCrmApiOrderClientHttpService $apiOrderClientHttpService,
        Bitrix                          $bitrix,
        ContactResponseMapper           $contactMapper
    )
    {
        $contacts = $apiOrderClientHttpService->getCustomer([
            'date' => now()->subDay()->endOfDay()->format('Y-m-d\TH:i:s.v')
        ]);

        $this->output->progressStart(count($contacts));

        foreach ($contacts as $contact) {
            $this->output->progressAdvance();

            try {
                $customerId = $contact['id'];

                $contacts = $bitrix->sendDataToBitrix('crm.contact.list', [
                    "filter" => ['UF_CRM_1763806272' => $customerId],
                    "select" => ['ID', 'NAME', 'UF_CRM_1763806272']
                ]);


                if ($contacts['total'] === 0) {
                    $data = new ContactFieldsDto(
                        name: $contact['name'],
                        phones: [
                            [
                                'VALUE' => $contact['phone1'],
                                'VALUE_TYPE' => 'MOBILE'
                            ],
                        ],
                        extra: [
                            'UF_CRM_1763806272' => $contact['id']
                        ]
                    );

                    $bitrix->sendDataToBitrix('crm.contact.add', [
                        'fields' => $data->toArray()
                    ]);
                }

                $contact = collect($contacts['result'])
                    ->where('UF_CRM_1763806272', $customerId)
                    ->first();

                $contactId = $contact['ID'];

                $debts = $apiOrderClientHttpService->getClientDebtByBusinessPartnerId($customerId);
                $contactExtraFields = array_merge(
                    $this->prepareContactExtraFields($customerId),
                    $this->mapDebtFields($debts)
                );

                if (!empty($contactExtraFields)) {
                    $bitrix->sendDataToBitrix('crm.contact.update', [
                        'ID' => $contactId,
                        'fields' => $contactExtraFields,
                    ]);
                }


            } catch (\Exception $e) {
                Log::error('sync-contact-from-sup-command', [
                    'message' => $e->getMessage(),
                    'contact' => $contact
                ]);
            }


        }

        $this->output->progressFinish();
    }


    private function prepareContactExtraFields(string $sapContactId): array
    {
        try {
            $customer = app(SupCrmApiOrderClientHttpService::class)->getCustomerById($sapContactId);
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
            'UF_CRM_1766518923' => app(SupCrmApiOrderClientHttpService::class)->findSalesPointByCustomerGroupId($groupId),
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
}
