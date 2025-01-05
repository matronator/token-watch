<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Tracy\Debugger;

class VelarService
{
    const API_URL = 'https://api.velar.co';

    public function __construct(public Explorer $database, public HiroService $hiroService)
    {
    }

    public function findAll(): Selection
    {
        return $this->database->table('velar_tickers')->order('liquidity_in_usd DESC, target_token_name');
    }

    public function findAllTokens(): Selection
    {
        return $this->database->table('velar_tokens')->order('CAST(price AS DOUBLE) DESC, name, symbol');
    }

    public function getTickerById(string $id): ?ActiveRow
    {
        return $this->findAll()->where('ticker_id', $id)->fetch();
    }

    public function getTokenByAddress(string $address): ?ActiveRow
    {
        return $this->findAllTokens()->where('contract_address', $address)->fetch();
    }

    public function findAllCollections(): Selection
    {
        return $this->database->table('collections')->where('dex', 'velar')->order('collected_at DESC');
    }

    public function collect()
    {
        try {
            $tickers = $this->loadTickers();
            $tokens = $this->loadTokens();

            $newTokens = 0;
            foreach ($tokens as $token) {
                $savedToken = $this->getTokenByAddress($token['contractAddress']);
                if (!$savedToken) {
                    $newTokens++;
                    $this->database->table('velar_tokens')->insert([
                        'contract_address' => $token['contractAddress'],
                        'name' => $token['name'],
                        'symbol' => $token['symbol'],
                        'price' => $token['price'],
                        'image_url' => $token['imageUrl'],
                        'asset_name' => $token['assetName'],
                        'social_links' => json_encode($token['socialLinks']),
                        'inserted_at' => new \DateTime(),
                    ]);
                } else {
                    $savedToken->update([
                        'price' => $token['price'],
                        'updated_at' => new \DateTime(),
                    ]);
                }
            }

            $newTicker = 0;
            foreach ($tickers as $ticker) {
                $savedTicker = $this->saveTicker($ticker);
                if (!$savedTicker) {
                    $newTicker++;
                }
            }

            $this->findAllCollections()->insert([
                'collected_at' => new \DateTime(),
                'new_pools' => $newTicker + $newTokens,
                'dex' => 'velar',
            ]);
        } catch (\Exception $e) {
            Debugger::log($e);
        }
    }

    public function saveTicker(array $ticker): bool
    {
        $velarToken = $this->findAllTokens()->where('contract_address', $ticker['target_currency'])->fetch();
        if ($this->getTickerById($ticker['ticker_id'])) {
            $this->findAll()->where('ticker_id', $ticker['ticker_id'])->update([
                'liquidity_in_usd' => $ticker['liquidity_in_usd'],
                'base_volume' => $ticker['base_volume'],
                'velar_token_id' => ($velarToken !== null) ? $velarToken->id : null,
                'target_volume' => $ticker['target_volume'],
                'last_price' => $ticker['last_price'],
                'updated_at' => new \DateTime(),
            ]);

            return false;
        }

        $token = $this->getTokenByAddress($ticker['target_currency']);
        $name = 'Unknown';
        $symbol = 'N/A';
        if ($token) {
            $name = $token->name;
            $symbol = $token->symbol;
        }

        $this->database->table('velar_tickers')->insert([
            'ticker_id' => $ticker['ticker_id'],
            'velar_token_id' => ($velarToken !== null) ? $velarToken->id : null,
            'base_currency' => $ticker['base_currency'],
            'target_currency' => $ticker['target_currency'],
            'pool_id' => $ticker['pool_id'],
            'liquidity_in_usd' => $ticker['liquidity_in_usd'],
            'base_volume' => $ticker['base_volume'],
            'target_volume' => $ticker['target_volume'],
            'last_price' => $ticker['last_price'],
            'target_token_name' => $name,
            'target_token_symbol' => $symbol,
            'inserted_at' => new \DateTime(),
        ]);

        return true;
    }

    public function loadTickers(): array
    {
        $client = new Client([
            'base_uri' => self::API_URL,
        ]);

        $response = $client->request('GET', 'tickers');

        return json_decode($response->getBody()->getContents(), true);
    }

    public function loadTokens(): array
    {
        $client = new Client([
            'base_uri' => self::API_URL,
        ]);

        $response = $client->request('GET', 'tokens');

        return json_decode($response->getBody()->getContents(), true);
    }

    public function getNewTickers(): array
    {
        return $this->findAll()->where('inserted_at > ?', new \DateTime('-2 day'))->order('inserted_at DESC, liquidity_in_usd DESC')->fetchAll();
    }

    public function getNewTickersWithLiquidity(): array
    {
        return $this->findAll()->where('inserted_at > ?', new \DateTime('-2 day'))->where('liquidity_in_usd > 0')->order('inserted_at DESC, liquidity_in_usd DESC')->fetchAll();
    }

    public function getLastCollection(): ?ActiveRow
    {
        return $this->database->table('collections')->where('dex', 'velar')->order('collected_at DESC')->fetch();
    }
}
