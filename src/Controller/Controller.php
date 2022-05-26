<?php

namespace App\Controller;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class Controller extends AbstractController
{

    public function index(): JsonResponse
    {
        $msg = $this->prepareMessage($this->getBankCurrencyRates(), $this->getStockCurrencyRates());

        $this->sendMessageToChat($msg);

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

        foreach ($currencyRates as $key => $value) {
            $currencyRates[$value['fromCurrency']['name']] = [
                'name' => $value['fromCurrency']['name'],
                'sell' => $value['buy'],
                'buy'  => $value['sell'],
            ];

            unset($currencyRates[$key]);
        }

        return $currencyRates;
    }

    private function prepareMessage(array $bankCurrencyRates, ?array $stockCurrencyRates = null): string
    {
        date_default_timezone_set('Europe/Moscow');

        $now = date('d.m.Y H:i:s') . ' Moscow';

        $usdString = "{$bankCurrencyRates['USD']['sell']}   {$bankCurrencyRates['USD']['buy']}";
        $eurString = "{$bankCurrencyRates['EUR']['sell']}   {$bankCurrencyRates['EUR']['buy']}";
        $gbpString = "{$bankCurrencyRates['GBP']['sell']}   {$bankCurrencyRates['GBP']['buy']}";

        return "<pre>{$now}\n\n<b>USD</b> {$usdString}\n<b>EUR</b> {$eurString}\n<b>GBP</b> {$gbpString}\n</pre>";
    }

    function sendMessageToChat(string $text)
    {
        $client = HttpClient::create();

        $chatId = $this->getParameter('app.telegram_chat_id');
        $botToken = $this->getParameter('app.telegram_bot_token');
        $response = $client->request(
            'POST',
            "https://api.telegram.org/bot{$botToken}/sendMessage",
            [
                'json' => [
                    'chat_id'    => $chatId,
                    'text'       => $text,
                    'parse_mode' => 'html',
                ],
            ]
        );

    }
}