<?php

namespace App\BitrixManager;

use Illuminate\Support\Facades\Http;

class Bitrix
{
    public function sendDataToBitrix($method, $data)
    {
       return Http::withoutVerifying()
            ->baseUrl(config('bitrix24.base_uri'))
            ->post($method, $data)
            ->json();
    }
}
