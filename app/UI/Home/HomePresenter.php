<?php

declare(strict_types=1);

namespace App\UI\Home;

use App\Services\StackSwapService;
use Nette;


final class HomePresenter extends Nette\Application\UI\Presenter
{
    public function __construct(public string $apiKey, public StackSwapService $stackSwapService)
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
        $this->template->allPools = $this->stackSwapService->getAllPools();
        $this->template->poolsWithLiquidity = $this->stackSwapService->getNewPoolsWithLiquidity();
    }
}
