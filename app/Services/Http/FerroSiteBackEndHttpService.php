<?php

namespace App\Services\Http;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class FerroSiteBackEndHttpService
{
    /**
     * @param array $query
     * @return array
     * @throws RequestException
     */
    public function listServiceAccountOrdersw(array $query = []): array
    {
        $query = array_merge([
            'minCreatedDate' => now()->subDays()->startOfDay()->format('Y-m-d\TH:i:s.v'),
            'maxCreatedDate' => now()->endOfDay()->format('Y-m-d\TH:i:s.v'),
        ]);

        $response = $this->baseRequest()
            ->post('/service-account/order/list', $query);

        $response->throw();

        return $response->json();
    }


    public function serviceAccountOrderById(int $orderId): array
    {
        $response = $this->baseRequest()
            ->get('/service-account/order/' . $orderId);

        $response->throw();

        return $response->json();
    }


    public function mapOrderToSup(array $payload): array
    {
        return [
            'businessPartnerId' => $payload['customerExternalId'],
            'dueDate' => Carbon::parse($payload['createdDate'])->format('Y-m-d'),
            'salesPointId' => 'Distribyutsiya',
            'comments' => 'Comment from bitrix', // nullable
            'transportationCode' => 1,
            'items' => $this->mapItems($payload['products'] ?? []),
        ];
    }

    private function mapItems(array $products): array
    {
        return collect($products)->map(function (array $item) {

            return [
                'id' => data_get($item, 'product.externalId'),
                'price' => (float)$item['productPrice'],
                'uPriceListPrice' => (float)$item['productPrice'],
                'discountPercent' => 0,
                'qty' => (int)$item['quantity'],
                "whsId" => "12",
                'comment' => "Order from bitrix24",
            ];

        })->values()->toArray();
    }

    private function baseRequest(): PendingRequest
    {
        $baseUrl = rtrim((string)config('services.ferro_site_backend.base_url'), '/');
        $token = (string)config('services.ferro_site_backend.token');

        if ($baseUrl === '') {
            throw new InvalidArgumentException('Ferro site backend base URL is not configured.');
        }

        if ($token === '') {
            throw new InvalidArgumentException('Ferro site backend token is not configured.');
        }

        return Http::withHeaders([
            'Accept' => 'application/json',
            'User-Agent' => 'Thunder Client (https://www.thunderclient.com)',
        ])
            ->baseUrl($baseUrl)
            ->withToken($token);
    }
}
