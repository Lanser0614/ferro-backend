<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function (\Doniyor\Bitrix24\Bitrix24Manager $manager) {
    dd($manager->crm()->deals()->get(30));
});

Route::view('/docs/api', 'docs.redoc')->name('docs.redoc');

Route::get('/docs/openapi.yaml', function () {
    $specPath = base_path('docs/openapi.yaml');

    abort_unless(file_exists($specPath), 404);

    return response()->file($specPath, [
        'Content-Type' => 'application/yaml',
    ]);
})->name('docs.openapi');
