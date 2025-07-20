<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PlaidService
{
    protected $clientId;
    protected $secret;
    protected $env;

    public function __construct()
    {
        $this->clientId = config('plaid.client_id');
        $this->secret = config('plaid.secret');
        $this->env = config('plaid.env');
    }

    protected function baseUrl()
    {
        return match ($this->env) {
            'sandbox' => 'https://sandbox.plaid.com',
            'development' => 'https://development.plaid.com',
            'production' => 'https://production.plaid.com',
        };
    }

    public function createLinkToken($userId)
    {
        $response = Http::post("{$this->baseUrl()}/link/token/create", [
            'client_id' => $this->clientId,
            'secret' => $this->secret,
            'client_name' => 'Laravel App',
            'language' => 'en',
            'country_codes' => ['US'],
            'user' => ['client_user_id' => (string) $userId],
            'products' => ['auth', 'transactions'],
        ]);

        return $response->json();
    }

    public function exchangePublicToken($publicToken)
    {
        $response = Http::post("{$this->baseUrl()}/item/public_token/exchange", [
            'client_id' => $this->clientId,
            'secret' => $this->secret,
            'public_token' => $publicToken,
        ]);

        return $response->json();
    }

    public function getTransactions($accessToken, $startDate, $endDate)
    {
        $response = Http::post("{$this->baseUrl()}/transactions/get", [
            'client_id' => $this->clientId,
            'secret' => $this->secret,
            'access_token' => $accessToken,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return $response->json();
    }
}
