<?php

namespace App\Console\Commands;

use App\BitrixManager\Bitrix;
use App\Services\Http\SupCrmApiOrderClientHttpService;
use Doniyor\Bitrix24\CRM\Mappers\ContactResponseMapper;
use Doniyor\Bitrix24\DTO\CRM\ContactFieldsDto;
use Illuminate\Console\Command;
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
            } catch (\Exception $e) {
                Log::error('sync-contact-from-sup-command', [
                    'message' => $e->getMessage(),
                    'contact' => $contact
                ]);
            }


        }

        $this->output->progressFinish();
    }
}
