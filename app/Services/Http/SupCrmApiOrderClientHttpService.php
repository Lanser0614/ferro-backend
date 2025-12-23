<?php

namespace App\Services\Http;

use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use JsonException;

class SupCrmApiOrderClientHttpService
{

    public function getClientDebtByBusinessPartnerId(?string $businessPartnerId = null)
    {
        return $this->baseRequest()
            ->get('/debt/list',[
                'businessPartnerId' => $businessPartnerId
            ])->json();
    }

    /**
     * @throws RequestException
     */
    public function listOrders(string $businessPartnerId): array
    {
        try {
            $payload = json_encode([
                'businessPartnerId' => $businessPartnerId,
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Unable to encode Ferro order payload.', 0, $exception);
        }

        $response = $this->baseRequest()
            ->withBody($payload, 'application/json')
            ->get('/order/list');

        $response->throw();

        return $response->json();
    }

    public function getOrderById(int $supOrderId): mixed
    {
        try {
            $data = $this->baseRequest()->get("/order/$supOrderId")->throw()->json();
        } catch (RequestException $exception) {
            Log::error('error_on_send_to_sup' , [
                'payload' => $supOrderId,
                'exception' => $exception
            ]);
        }
        return $data;
    }

    /**
     * @param array $payload
     * @return array|mixed
     */
    public function createOrder(array $payload): mixed
    {
        try {
            $data = $this->baseRequest()->post('/order', $payload)->throw()->json();
        } catch (RequestException $exception) {
            Log::error('error_on_send_to_sup' , [
                'payload' => $payload,
                'exception' => $exception
            ]);
        }
        return $data;
    }

    /**
     * @param string $phone
     * @throws RequestException
     */
    public function getCustomerByPhone(string $phone)
    {
        $response = $this->baseRequest()
            ->post(' /customer/list/by-phone?phone=' . $phone)->throw()->json();
    }

    private function baseRequest(): PendingRequest
    {
        $baseUrl = rtrim((string)config('services.ferro.base_url'), '/');
        $token = (string)config('services.ferro.token');

        if ($baseUrl === '') {
            throw new InvalidArgumentException('Ferro API base URL is not configured.');
        }

        if ($token === '') {
            throw new InvalidArgumentException('Ferro API token is not configured.');
        }

        return Http::withHeaders([
            'Accept' => '*/*',
            'User-Agent' => 'Thunder Client (https://www.thunderclient.com)',
            'Content-Type' => 'application/json',
        ])
            ->baseUrl($baseUrl)
            ->withToken($token);
    }
}
