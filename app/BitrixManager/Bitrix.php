<?php

namespace App\BitrixManager;

class Bitrix
{
    public function sendDataToBitrix($method, $data)
    {
        $queryUrl = config('bitrix24.base_uri') . $method;
        $queryData = http_build_query($data);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_POST => 1,
            CURLOPT_HEADER => 0,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $queryUrl,
            CURLOPT_POSTFIELDS => $queryData,
        ]);

        $result = curl_exec($curl);
        curl_close($curl);

        return json_decode($result);
    }
}
