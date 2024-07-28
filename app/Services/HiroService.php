<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use Tracy\Debugger;

class HiroService
{
    const API_URL = 'https://api.mainnet.hiro.so';

    public function __construct(public string $apiKey)
    {
    }

    public function getTokenName(string $contractId): string
    {
        $contractInfo = $this->getContractInfo($contractId);

        $name = '';
        try {
            $name = json_decode($contractInfo['abi'])[0]->name;
        } catch (\Exception $e) {
            Debugger::log($e, Debugger::EXCEPTION);
            $name = 'Unknown';
        }

        return $name;
    }

    public function getContractInfo(string $contractId): array
    {
        $response = $this->sendRequest('extended/v1/contract/' . $contractId);

        return json_decode($response->getBody()->getContents(), true);
    }

    public function sendRequest(string $url, string $method = 'GET')
    {
        $client = new Client([
            'base_uri' => self::API_URL,
        ]);

        return $client->request($method, $url, [
            'headers' => [
                'X-API-Key' => $this->apiKey,
            ],
        ]);
    }
}
