<?php

declare(strict_types=1);

namespace App\UI\Home;

use App\Services\HiroService;
use App\Services\StackSwapService;
use App\Services\VelarService;
use Nette;
use Tracy\Debugger;

final class HomePresenter extends Nette\Application\UI\Presenter
{
    public function __construct(public string $apiKey, public StackSwapService $stackSwapService, public HiroService $hiroService, public VelarService $velarService)
    {
        parent::__construct();
    }

    public function actionCollect(string $key): void
    {
        if ($key !== $this->apiKey) {
            $this->sendJson(['status' => 'error', 'message' => 'Invalid key']);
            return;
        }

        $this->stackSwapService->collect();
        $this->velarService->collect();
        $this->sendJson(['status' => 'ok']);
    }

    public function renderDefault(): void
    {
        $collection = $this->stackSwapService->getLastCollection();
        if ($collection) {
            $this->template->collectedAt = $collection->collected_at;
        } else {
            $this->template->collectedAt = new \DateTime();
        }

        $this->template->pools = $this->stackSwapService->getNewPools();
        $this->template->tickers = $this->velarService->getNewTickers();
        $this->template->allPools = $this->stackSwapService->getAllPools();
        $this->template->allTickers = $this->velarService->findAll()->fetchAll();
        $this->template->poolsWithLiquidity = $this->stackSwapService->getNewPoolsWithLiquidity();
        $this->template->tickersWithLiquidity = $this->velarService->getNewTickersWithLiquidity();
    }
}
