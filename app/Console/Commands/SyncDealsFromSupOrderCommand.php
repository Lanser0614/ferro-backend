<?php

namespace App\Console\Commands;

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
        SupCrmApiOrderClientHttpService $apiOrderClientHttpService
    )
    {
        SupOrder::query()->where('status', '=', 'new')->chunkById(100, function ($supOrders) use ($apiOrderClientHttpService) {
            foreach ($supOrders as $supOrder) {
                $order = $apiOrderClientHttpService->getOrderById($supOrder->sup_order_id);
            }
        });
    }
}
