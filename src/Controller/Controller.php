<?php

namespace App\Controller;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\JsonResponse;

class Controller
{

    public function index(): JsonResponse
    {
        return new JsonResponse(
            [
                'bank'  => $this->getBankCurrencyRates(),
                'stock' => $this->getStockCurrencyRates(),
            ]
        );
    }

    private function getStockCurrencyRates(): array
    {
        return [];
    }

    private function getBankCurrencyRates(): array
    {
        $client = HttpClient::create();

        $currencyRates = $client->request('GET', 'https://api.tinkoff.ru/v1/currency_rates')->getContent();
        $currencyRates = json_decode($currencyRates, true, 512, JSON_THROW_ON_ERROR);

        $currencyRates = array_filter($currencyRates['payload']['rates'],
            static function ($item) {
                return $item['category'] === 'DepositPayments' && $item['toCurrency']['name'] === 'RUB';
            }
        );

        $currencyRates = array_map(static function ($value) {
            return [
                'name' => $value['fromCurrency']['name'],
                'sell' => $value['buy'],
                'buy'  => $value['sell'],
            ];
        }, $currencyRates);

        return array_values($currencyRates);
    }
}