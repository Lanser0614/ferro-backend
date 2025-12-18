<?php

namespace App\Console\Commands;

use App\Services\Http\FerroSiteBackEndHttpService;
use App\UseCase\Bitrix\BitrixOrderSyncUseCase;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

class SyncOrderFromFerroSiteCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-order-from-ferro-site-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     * @param BitrixOrderSyncUseCase $bitrixOrderSyncUseCase
     * @param FerroSiteBackEndHttpService $backEndHttpService
     * @throws RequestException
     */
    public function handle(
        BitrixOrderSyncUseCase $bitrixOrderSyncUseCase,
        FerroSiteBackEndHttpService $backEndHttpService
    ): void
    {

        Log::debug('artisan', [
            'app:sync-order-from-ferro-site-command'
        ]);
        $this->info('Start sync orders from Ferro site');

        $orders = $backEndHttpService->listServiceAccountOrdersw();

        $this->output->progressStart(count($orders['result']));

        foreach ($orders['result'] as $order) {
            $this->output->progressAdvance();

            $bitrixOrderSyncUseCase->sync($order);
        }

    }
}
