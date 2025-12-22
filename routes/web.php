<?php

use App\Services\Http\FerroSiteBackEndHttpService;
use Illuminate\Support\Facades\Route;

Route::get('/', function (
    FerroSiteBackEndHttpService $backEndHttpService,
    \App\UseCase\Bitrix\BitrixOrderSyncUseCase $useCase
) {
    $order = $backEndHttpService->serviceAccountOrderById(1080079);
    $useCase->sync($order);
});

Route::view('/docs/api', 'docs.redoc')->name('docs.redoc');

Route::get('/docs/openapi.yaml', function () {
    $specPath = base_path('docs/openapi.yaml');

    abort_unless(file_exists($specPath), 404);

    return response()->file($specPath, [
        'Content-Type' => 'application/yaml',
    ]);
})->name('docs.openapi');
