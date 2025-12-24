<?php

namespace App\Console\Commands;

use App\BitrixManager\Bitrix;
use App\Models\SupOrder;
use App\Services\Http\SupCrmApiOrderClientHttpService;
use Illuminate\Console\Command;

class SyncDealsFromSupOrderCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-deals-from-sup-order-command';

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
        Bitrix $bitrix
    )
    {
        SupOrder::query()
            ->where('status', '=', 'new')
            ->whereDate('created_at', '>=', now()->subDays(10))
            ->chunkById(100, function ($supOrders) use ($apiOrderClientHttpService, $bitrix) {
            foreach ($supOrders as $supOrder) {
                $order = $apiOrderClientHttpService->getOrderById($supOrder->sup_order_id);
                if ($order['status'] != 'Closed') {
                    $bitrix->sendDataToBitrix(
                        'crm.deal.update',
                        [
                            'id' => $supOrder->bitrix_deal_id,
                            'fields' => [
                                'UF_CRM_1766123056' => $order['status'],
                            ],
                        ]
                    );
                }
            }
        });
    }
}
