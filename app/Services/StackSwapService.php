<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class StackSwapService
{
    public function __construct(public Explorer $database)
    {
    }

    public function getPools(): Selection
    {
        return $this->database->table('stackswap_pools');
    }

    /**
     * @return ActiveRow[]
     */
    public function getAllPools(): array
    {
        return $this->database->table('stackswap_pools')->fetchAll();
    }

    public function getPoolByIdentifier(int $identifier): mixed|ActiveRow|null
    {
        return $this->database->table('stackswap_pools')->where('identifier', $identifier)->fetch();
    }

    public function collect()
    {
        $client = new Client();
        $res = $client->request('GET', 'https://app.stackswap.org/api/v1/pools');
        $pools = json_decode($res->getBody()->getContents());

        $newPools = 0;

        foreach ($pools as $pool) {
            $savedPool = $this->getPoolByIdentifier($pool->identifier);
            if (!$savedPool) {
                $newPools++;
                $this->database->table('stackswap_pools')->insert([
                    'identifier' => $pool->identifier,
                    'pair_name' => $pool->pair_name,
                    'token_address_x' => $pool->token_address_x,
                    'token_address_y' => $pool->token_address_y,
                    'liquidity_token_addr' => $pool->liquidity_token_addr,
                    'liquidity_locked' => rtrim($pool->liquidity_locked, 'USD'),
                    'inserted_at' => new \DateTime(),
                ]);
            }
        }

        $this->database->table('collections')->insert([
            'collected_at' => new \DateTime(),
            'new_pools' => $newPools,
        ]);
    }

    public function getNewPools(): array
    {
        return $this->getPools()->where('inserted_at > ?', new \DateTime('-2 day'))->order('inserted_at DESC, liquidity_locked DESC')->fetchAll();
    }

    public function getNewPoolsWithLiquidity(): array
    {
        return $this->getPools()->where('inserted_at > ?', new \DateTime('-2 day'))->where('liquidity_locked > 0')->order('inserted_at DESC, liquidity_locked DESC')->fetchAll();
    }
}